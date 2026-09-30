<?php

namespace App\Enums;

/**
 * Progress of an asynchronously generated document file.
 */
enum PdfStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
