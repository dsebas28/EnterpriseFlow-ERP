<?php

namespace App\Webhooks;

use RuntimeException;

/**
 * A permanent failure: retrying the event would fail the same way
 * (unknown company or document, inconsistent amounts...).
 */
class UnprocessableWebhook extends RuntimeException {}
