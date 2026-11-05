<?php

namespace App\Domain\Ai;

use App\Exceptions\DomainActionException;

/** The provider could not produce a usable answer (transport, auth, refusal, truncation). */
class GenerationFailed extends DomainActionException {}
