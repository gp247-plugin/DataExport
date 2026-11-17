<?php

namespace App\GP247\Plugins\DataExport\Domain;

/**
 * The kinds of data the plugin exports and the permission group of each (catalog, customers,
 * orders — customers and orders hold personal data and are granted apart).
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-entity-csv
 * @aidlc-story US-data-export-access-control
 */
final class ExportKind
{
    public const CATEGORY = 'category';
    public const BRAND = 'brand';
    public const SUPPLIER = 'supplier';
    public const PRODUCT = 'product';
    public const CUSTOMER = 'customer';
    public const ORDER = 'order';

    /** Every kind, in the order a Data Import of the files must follow. */
    public const ALL = [self::CATEGORY, self::BRAND, self::SUPPLIER, self::PRODUCT, self::CUSTOMER, self::ORDER];

    public const GROUP_CATALOG = 'catalog';
    public const GROUP_CUSTOMERS = 'customers';
    public const GROUP_ORDERS = 'orders';

    public const GROUPS = [self::GROUP_CATALOG, self::GROUP_CUSTOMERS, self::GROUP_ORDERS];

    /**
     * Whether $kind is a kind of data the plugin knows.
     *
     * @param string $kind
     * @return bool
     */
    public static function isKnown(string $kind): bool
    {
        return in_array($kind, self::ALL, true);
    }

    /**
     * Permission group of a kind.
     *
     * @param string $kind
     * @return string
     * @throws \InvalidArgumentException On an unknown kind.
     */
    public static function group(string $kind): string
    {
        return match ($kind) {
            self::CATEGORY, self::BRAND, self::SUPPLIER, self::PRODUCT => self::GROUP_CATALOG,
            self::CUSTOMER => self::GROUP_CUSTOMERS,
            self::ORDER => self::GROUP_ORDERS,
            default => throw new \InvalidArgumentException('Unknown kind of data.'),
        };
    }
}
