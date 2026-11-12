<?php

declare(strict_types=1);

namespace App\Support\QrCode;

use InvalidArgumentException;

/**
 * A small QR code encoder (ISO/IEC 18004, byte mode, error correction M,
 * versions 1 to 9: up to 180 bytes, plenty for an invitation link). It only
 * builds the module matrix and an SVG path for it; no image library needed.
 */
final class QrCode
{
    /** Quiet zone around the symbol, in modules (the standard asks for 4). */
    public const QUIET_ZONE = 4;

    /**
     * Error correction level M, per version: [EC codewords per block,
     * [blocks, data codewords per block], ...].
     *
     * @var array<int, array{0: int, 1: list<array{0: int, 1: int}>}>
     */
    private const BLOCKS = [
        1 => [10, [[1, 16]]],
        2 => [16, [[1, 28]]],
        3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]],
        8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]],
    ];

    /**
     * Centres of the alignment patterns, per version.
     *
     * @var array<int, list<int>>
     */
    private const ALIGNMENT = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
    ];

    /**
     * @param  list<list<bool>>  $modules  rows of modules, true = dark
     */
    private function __construct(
        public readonly int $version,
        public readonly int $mask,
        private readonly array $modules,
    ) {}

    /**
     * @param  int|null  $mask  force a mask pattern (0 to 7); the best one is picked when null
     *
     * @throws InvalidArgumentException when the text does not fit in a version 9 symbol
     */
    public static function encode(string $text, ?int $mask = null): self
    {
        $version = self::versionFor(strlen($text));
        $codewords = self::interleave($version, self::dataCodewords($version, $text));

        $size = 17 + 4 * $version;
        $modules = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = $modules;

        self::drawFunctionPatterns($version, $modules, $reserved);
        self::drawCodewords($codewords, $modules, $reserved);

        $best = null;
        $bestPenalty = PHP_INT_MAX;

        foreach ($mask === null ? range(0, 7) : [$mask] as $pattern) {
            $candidate = self::applyMask($pattern, $modules, $reserved);
            self::drawFormatBits($pattern, $candidate);
            $penalty = $mask === null ? self::penalty($candidate) : 0;

            if ($penalty < $bestPenalty) {
                [$best, $bestPenalty, $chosen] = [$candidate, $penalty, $pattern];
            }
        }

        if ($best === null || ! isset($chosen)) {
            throw new InvalidArgumentException('Unknown QR mask pattern.');
        }

        return new self($version, $chosen, $best);
    }

    /**
     * @return list<list<bool>> rows of modules, true = dark (no quiet zone)
     */
    public function modules(): array
    {
        return $this->modules;
    }

    /** Modules per side, without the quiet zone. */
    public function size(): int
    {
        return count($this->modules);
    }

    /** Side of the SVG view box: the symbol plus the quiet zone on both sides. */
    public function viewBoxSize(): int
    {
        return $this->size() + 2 * self::QUIET_ZONE;
    }

    /**
     * Path data for the dark modules, in a view box of viewBoxSize() units:
     * one horizontal run per group of adjacent dark modules.
     */
    public function svgPath(): string
    {
        $path = '';

        foreach ($this->modules as $y => $row) {
            $run = 0;

            for ($x = 0, $size = count($row); $x <= $size; $x++) {
                if ($x < $size && $row[$x]) {
                    $run++;

                    continue;
                }

                if ($run > 0) {
                    $path .= 'M'.($x - $run + self::QUIET_ZONE).' '.($y + self::QUIET_ZONE).'h'.$run.'v1h-'.$run.'z';
                    $run = 0;
                }
            }
        }

        return $path;
    }

    private static function versionFor(int $bytes): int
    {
        foreach (self::BLOCKS as $version => [, $groups]) {
            // Mode (4 bits) + length (8 bits) take two codewords (rounded up).
            if ($bytes <= self::dataCapacity($groups) - 2) {
                return $version;
            }
        }

        throw new InvalidArgumentException('Text is too long for a QR code: '.$bytes.' bytes.');
    }

    /**
     * @param  list<array{0: int, 1: int}>  $groups
     */
    private static function dataCapacity(array $groups): int
    {
        return array_sum(array_map(fn (array $group): int => $group[0] * $group[1], $groups));
    }

    /**
     * Mode, length, data, terminator and padding: exactly the data capacity.
     *
     * @return list<int>
     */
    private static function dataCodewords(int $version, string $text): array
    {
        $capacity = self::dataCapacity(self::BLOCKS[$version][1]);
        $bits = '0100'.str_pad(decbin(strlen($text)), 8, '0', STR_PAD_LEFT);

        foreach (unpack('C*', $text) ?: [] as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);

        $codewords = array_map(bindec(...), str_split($bits, 8));

        for ($pad = 0xEC; count($codewords) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $codewords[] = $pad;
        }

        return array_map(intval(...), $codewords);
    }

    /**
     * Splits the data into blocks, adds Reed-Solomon codewords to each and
     * interleaves them in transmission order.
     *
     * @param  list<int>  $data
     * @return list<int>
     */
    private static function interleave(int $version, array $data): array
    {
        [$ecLength, $groups] = self::BLOCKS[$version];
        $generator = self::generatorPolynomial($ecLength);
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        foreach ($groups as [$count, $length]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($data, $offset, $length);
                $offset += $length;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::remainder($block, $generator);
            }
        }

        $result = [];
        $longest = max(array_map(count(...), $dataBlocks));

        for ($i = 0; $i < $longest; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecLength; $i++) {
            foreach ($ecBlocks as $block) {
                $result[] = $block[$i];
            }
        }

        return $result;
    }

    /** Multiplication in GF(256) with the QR polynomial x^8 + x^4 + x^3 + x^2 + 1. */
    private static function multiply(int $a, int $b): int
    {
        $product = 0;

        for (; $b > 0; $b >>= 1) {
            if ($b & 1) {
                $product ^= $a;
            }

            $a <<= 1;

            if ($a & 0x100) {
                $a ^= 0x11D;
            }
        }

        return $product;
    }

    /**
     * Coefficients of (x - 2^0)(x - 2^1)...(x - 2^(n-1)), highest degree
     * first, without the leading 1.
     *
     * @return list<int>
     */
    private static function generatorPolynomial(int $degree): array
    {
        $polynomial = array_fill(0, $degree, 0);
        $polynomial[$degree - 1] = 1;
        $root = 1;

        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $polynomial[$j] = self::multiply($polynomial[$j], $root) ^ ($polynomial[$j + 1] ?? 0);
            }

            $root = self::multiply($root, 2);
        }

        return $polynomial;
    }

    /**
     * Reed-Solomon error correction codewords for one block.
     *
     * @param  list<int>  $data
     * @param  list<int>  $generator
     * @return list<int>
     */
    private static function remainder(array $data, array $generator): array
    {
        $remainder = array_fill(0, count($generator), 0);

        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($remainder);
            $remainder[] = 0;

            foreach ($generator as $i => $coefficient) {
                $remainder[$i] ^= self::multiply($coefficient, $factor);
            }
        }

        return $remainder;
    }

    /**
     * Finder, timing and alignment patterns, the dark module, the version
     * information and the cells kept for the format information.
     *
     * @param  list<list<bool>>  $modules
     * @param  list<list<bool>>  $reserved
     */
    private static function drawFunctionPatterns(int $version, array &$modules, array &$reserved): void
    {
        $size = count($modules);
        $set = function (int $x, int $y, bool $dark) use (&$modules, &$reserved, $size): void {
            if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                $modules[$y][$x] = $dark;
                $reserved[$y][$x] = true;
            }
        };

        for ($i = 0; $i < $size; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }

        // Finder patterns with their light separators.
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $ring = max(abs($dx), abs($dy));
                    $set($cx + $dx, $cy + $dy, $ring !== 2 && $ring !== 4);
                }
            }
        }

        $centres = self::ALIGNMENT[$version];
        $last = count($centres) - 1;

        foreach ($centres as $i => $cy) {
            foreach ($centres as $j => $cx) {
                // The three corners with a finder pattern have none.
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }

                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }

        // Format information cells (filled in per mask) and the dark module.
        for ($i = 0; $i < 9; $i++) {
            $set(8, $i, $modules[$i][8]);
            $set($i, 8, $modules[8][$i]);
        }

        for ($i = 0; $i < 8; $i++) {
            $set($size - 1 - $i, 8, false);
            $set(8, $size - 1 - $i, false);
        }

        $set(8, $size - 8, true);

        if ($version >= 7) {
            $bits = self::bch($version, 0x1F25, 12);

            for ($i = 0; $i < 18; $i++) {
                $dark = (($bits >> $i) & 1) === 1;
                $a = $size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $set($a, $b, $dark);
                $set($b, $a, $dark);
            }
        }
    }

    /** Appends the BCH check bits to a value (format and version information). */
    private static function bch(int $value, int $generator, int $checkBits): int
    {
        $remainder = $value << $checkBits;

        for ($bit = (int) floor(log($remainder ?: 1, 2)); $bit >= $checkBits; $bit--) {
            if (($remainder >> $bit) & 1) {
                $remainder ^= $generator << ($bit - $checkBits);
            }
        }

        return ($value << $checkBits) | $remainder;
    }

    /**
     * Places the codewords in the zigzag: two columns at a time from the
     * right, alternating upwards and downwards, skipping function patterns.
     *
     * @param  list<int>  $codewords
     * @param  list<list<bool>>  $modules
     * @param  list<list<bool>>  $reserved
     */
    private static function drawCodewords(array $codewords, array &$modules, array $reserved): void
    {
        $size = count($modules);
        $total = count($codewords) * 8;
        $index = 0;
        $upwards = true;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // the vertical timing pattern takes a whole column
            }

            for ($step = 0; $step < $size; $step++) {
                $y = $upwards ? $size - 1 - $step : $step;

                foreach ([$right, $right - 1] as $x) {
                    if ($reserved[$y][$x]) {
                        continue;
                    }

                    if ($index < $total) {
                        $modules[$y][$x] = (($codewords[$index >> 3] >> (7 - ($index & 7))) & 1) === 1;
                    }

                    $index++;
                }
            }

            $upwards = ! $upwards;
        }
    }

    /**
     * @param  list<list<bool>>  $modules
     * @param  list<list<bool>>  $reserved
     * @return list<list<bool>>
     */
    private static function applyMask(int $pattern, array $modules, array $reserved): array
    {
        foreach ($modules as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($reserved[$y][$x]) {
                    continue;
                }

                $invert = match ($pattern) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => throw new InvalidArgumentException('Unknown QR mask pattern.'),
                };

                $modules[$y][$x] = $dark !== $invert;
            }
        }

        return $modules;
    }

    /**
     * Writes the two copies of the format information (level M + mask).
     *
     * @param  list<list<bool>>  $modules
     */
    private static function drawFormatBits(int $pattern, array &$modules): void
    {
        $size = count($modules);
        // Level M is 00, so the five data bits are just the mask pattern.
        $bits = self::bch($pattern, 0x537, 10) ^ 0x5412;
        $bit = fn (int $i): bool => (($bits >> $i) & 1) === 1;

        // First copy, around the top-left finder pattern.
        for ($i = 0; $i <= 5; $i++) {
            $modules[$i][8] = $bit($i);
        }

        $modules[7][8] = $bit(6);
        $modules[8][8] = $bit(7);
        $modules[8][7] = $bit(8);

        for ($i = 9; $i < 15; $i++) {
            $modules[8][14 - $i] = $bit($i);
        }

        // Second copy, split between the other two finder patterns.
        for ($i = 0; $i < 8; $i++) {
            $modules[8][$size - 1 - $i] = $bit($i);
        }

        for ($i = 8; $i < 15; $i++) {
            $modules[$size - 15 + $i][8] = $bit($i);
        }
    }

    /**
     * Penalty score of a masked symbol (lower is easier to scan): the four
     * rules of the standard.
     *
     * @param  list<list<bool>>  $modules
     */
    private static function penalty(array $modules): int
    {
        $size = count($modules);
        $penalty = 0;
        $dark = 0;
        $columns = [];

        foreach ($modules as $y => $row) {
            foreach ($row as $x => $module) {
                $columns[$x][$y] = $module;
                $dark += $module ? 1 : 0;

                // Rule 2: 2×2 blocks of one colour.
                if ($x + 1 < $size && $y + 1 < $size
                    && $module === $row[$x + 1] && $module === $modules[$y + 1][$x] && $module === $modules[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        foreach ([...$modules, ...$columns] as $line) {
            $text = implode('', array_map(fn (bool $module): string => $module ? '1' : '0', $line));

            // Rule 1: runs of five or more modules of one colour.
            preg_match_all('/0{5,}|1{5,}/', $text, $runs);

            foreach ($runs[0] as $run) {
                $penalty += strlen($run) - 2;
            }

            // Rule 3: finder-like patterns next to four light modules.
            $penalty += 40 * (preg_match_all('/(?=10111010000)/', $text) + preg_match_all('/(?=00001011101)/', $text));
        }

        // Rule 4: distance of the dark share from 50 %, in steps of 5 %.
        return $penalty + 10 * intdiv((int) abs($dark * 20 - $size * $size * 10), $size * $size);
    }
}
