<?php

namespace MrNewport\LaravelDocSign\Services\Signature;

use RuntimeException;

/** A request may have reached the provider; the same transaction must be reconciled. */
final class PrivateSignatureSubmissionUnknown extends RuntimeException {}
