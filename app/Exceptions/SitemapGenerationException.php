<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the XML sitemap cannot be generated or published. The previously
 * published sitemap is always left in place when this is thrown.
 */
class SitemapGenerationException extends RuntimeException {}
