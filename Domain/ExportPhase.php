<?php

namespace App\GP247\Plugins\DataExport\Domain;

/**
 * Life of an export run: queued → building → ready → expired | deleted; cancelled and failed end
 * a run early. Only a ready run has a file.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-sliced-build
 */
final class ExportPhase
{
    public const QUEUED = 'queued';
    public const BUILDING = 'building';
    public const READY = 'ready';
    public const EXPIRED = 'expired';
    public const DELETED = 'deleted';
    public const CANCELLED = 'cancelled';
    public const FAILED = 'failed';

    /** Phases a slice still has work in. */
    public const ACTIVE = [self::QUEUED, self::BUILDING];

    /** Phases a run never leaves. */
    public const FINAL = [self::EXPIRED, self::DELETED, self::CANCELLED, self::FAILED];

    public const ALL = [self::QUEUED, self::BUILDING, self::READY, self::EXPIRED, self::DELETED, self::CANCELLED, self::FAILED];
}
