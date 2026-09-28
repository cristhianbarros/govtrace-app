<?php

namespace App\Domain\Configuration\Exceptions;

use DomainException;

/** US-038-CFG: a value that doesn't fit the parameter, with what it should be. */
class ParameterValueRejected extends DomainException {}
