<?php

namespace App\GP247\Plugins\DataExport\Support;

use App\GP247\Plugins\DataExport\Domain\ExportKind;
use App\GP247\Plugins\DataExport\Models\ExportJob;
use GP247\Core\AdminShell\Application\AuthorizeAdminAction;
use GP247\Core\AdminShell\Domain\AdminUserContract;
use GP247\Core\AdminShell\Domain\AuthorizationDecision;
use GP247\Core\AdminShell\Domain\AuthorizationException;

/**
 * Who may export what. Every action is authorized on the server against the URI of its group —
 * `<admin>/data-export/<catalog|customers|orders>/<run id | new>` — with core's URI permission
 * matcher; only a session at the root store context may use the plugin (a store-admin of
 * MultiStore Pro is refused), and a run is only reachable by the admin who created it.
 *
 * @aidlc-unit data-export
 * @aidlc-story US-data-export-access-control
 * @aidlc-adr ADR-data-export-download-protection
 */
final class Access
{
    public const URI = 'data-export';

    /**
     * Admin path of the plugin, e.g. "gp247_admin/data-export".
     *
     * @return string
     */
    public static function base(): string
    {
        return (defined('GP247_ADMIN_PREFIX') ? GP247_ADMIN_PREFIX : 'gp247_admin') . '/' . self::URI;
    }

    /**
     * Whether the session is at the root store context.
     *
     * @return bool
     */
    public static function isRoot(): bool
    {
        return (string) session('adminStoreId', GP247_STORE_ID_ROOT) === (string) GP247_STORE_ID_ROOT;
    }

    /**
     * Refuse a session that is not at the root store context.
     *
     * @return void
     * @throws AuthorizationException
     */
    public static function assertRoot(): void
    {
        if (!self::isRoot()) {
            throw AuthorizationException::fromReason('data-export: root store context only');
        }
    }

    /**
     * Whether the current admin may export the kinds of a group.
     *
     * @param string $group
     * @return bool
     */
    public static function allows(string $group): bool
    {
        try {
            return self::isRoot() && self::decide($group, 'new', 'create')->isAllowed();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Refuse the action unless the session is root and the admin holds the group's permission.
     *
     * @param string $group
     * @param string $target Run id or "new".
     * @param string $action Method being run.
     * @return void
     * @throws AuthorizationException
     */
    public static function authorize(string $group, string $target, string $action): void
    {
        self::assertRoot();
        $decision = self::decide($group, $target, $action);
        if (!$decision->isAllowed()) {
            throw AuthorizationException::fromReason($decision->reason());
        }
    }

    /**
     * Kinds the current admin may export, in import order.
     *
     * @return list<string>
     */
    public static function exportableKinds(): array
    {
        $groups = [];
        $kinds = [];
        foreach (ExportKind::ALL as $kind) {
            $group = ExportKind::group($kind);
            $groups[$group] ??= self::allows($group);
            if ($groups[$group]) {
                $kinds[] = $kind;
            }
        }

        return $kinds;
    }

    /**
     * Id of the current admin.
     *
     * @return string
     */
    public static function adminId(): string
    {
        return (string) (admin()->user()->id ?? '');
    }

    /**
     * A run of the current admin; any other id is "not found" (no hint that it exists).
     *
     * @param string $id
     * @return ExportJob
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public static function ownJob(string $id): ExportJob
    {
        $job = preg_match('/^[0-9a-z]{26}$/', $id) ? ExportJob::whereKey($id)->where('admin_id', self::adminId())->first() : null;
        if ($job === null) {
            abort(404);
        }

        return $job;
    }

    /**
     * @param string $group
     * @param string $target
     * @param string $action
     * @return AuthorizationDecision
     */
    private static function decide(string $group, string $target, string $action): AuthorizationDecision
    {
        return app(AuthorizeAdminAction::class)->authorize(app(AdminUserContract::class), self::base() . '/' . $group . '/' . $target, $action);
    }
}
