<?php

declare(strict_types=1);

namespace App\Support\Rbac;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for the fine-grained permission catalog and the
 * standard per-company role bundles. Roles and permissions are global (team_id
 * null); role *assignments* are scoped per company (the spatie team). Roles are
 * named by their App\Enums\CompanyRole value so the membership pivot and the
 * spatie assignment stay in lock-step.
 *
 * Enforcement lives in Actions and Filament gates via
 * User::hasCompanyPermission(); this class only provisions the catalog.
 */
final class RbacRegistry
{
    public const GUARD = 'web';

    // Ledger
    public const JOURNAL_CREATE = 'journal.create';

    public const JOURNAL_SUBMIT = 'journal.submit';

    public const JOURNAL_APPROVE = 'journal.approve';

    public const JOURNAL_POST = 'journal.post';

    public const JOURNAL_REVERSE = 'journal.reverse';

    // Receivables
    public const INVOICE_MANAGE = 'invoice.manage';

    public const INVOICE_POST = 'invoice.post';

    public const INVOICE_VOID = 'invoice.void';

    public const CREDITMEMO_MANAGE = 'creditmemo.manage';

    public const CREDITMEMO_POST = 'creditmemo.post';

    public const PAYMENT_RECEIVE = 'payment.receive';

    // Payables
    public const BILL_MANAGE = 'bill.manage';

    public const BILL_POST = 'bill.post';

    public const BILL_PAY = 'bill.pay';

    public const PAYMENT_PAY = 'payment.pay';

    // Banking
    public const BANK_RECORD = 'bank.record';

    public const BANK_RECONCILE = 'bank.reconcile';

    // Inventory & assets
    public const INVENTORY_ADJUST = 'inventory.adjust';

    public const ASSET_MANAGE = 'asset.manage';

    public const ASSET_DEPRECIATE = 'asset.depreciate';

    public const ASSET_DISPOSE = 'asset.dispose';

    // Periods & recurring
    public const PERIOD_MANAGE = 'period.manage';

    public const PERIOD_CLOSE = 'period.close';

    public const YEAR_CLOSE = 'year.close';

    public const RECURRING_MANAGE = 'recurring.manage';

    public const RECURRING_RUN = 'recurring.run';

    // Chart of accounts & tax
    public const ACCOUNT_MANAGE = 'account.manage';

    public const TAX_VIEW = 'tax.view';

    public const TAX_RETURNS_MANAGE = 'tax.returns.manage';

    // Cross-cutting
    public const REPORTS_VIEW = 'reports.view';

    public const AUDIT_VIEW = 'audit.view';

    public const COMPANY_MANAGE = 'company.manage';

    public const COMPANY_EXPORT = 'company.export';

    public const USERS_MANAGE = 'users.manage';

    /**
     * Role bundles, keyed by CompanyRole value. Designed to preserve the
     * pre-RBAC semantics of CompanyRole::canPost/canApprove/canManageCompany
     * for the original four roles, with Approver added.
     *
     * @return array<string, list<string>>
     */
    public static function roles(): array
    {
        $accountant = [
            self::JOURNAL_CREATE, self::JOURNAL_SUBMIT, self::JOURNAL_APPROVE, self::JOURNAL_POST, self::JOURNAL_REVERSE,
            self::INVOICE_MANAGE, self::INVOICE_POST, self::INVOICE_VOID, self::CREDITMEMO_MANAGE, self::CREDITMEMO_POST, self::PAYMENT_RECEIVE,
            self::BILL_MANAGE, self::BILL_POST, self::BILL_PAY, self::PAYMENT_PAY,
            self::BANK_RECORD, self::BANK_RECONCILE,
            self::INVENTORY_ADJUST, self::ASSET_MANAGE, self::ASSET_DEPRECIATE, self::ASSET_DISPOSE,
            self::PERIOD_MANAGE, self::PERIOD_CLOSE, self::YEAR_CLOSE, self::RECURRING_MANAGE, self::RECURRING_RUN,
            self::ACCOUNT_MANAGE, self::TAX_VIEW, self::TAX_RETURNS_MANAGE, self::REPORTS_VIEW,
        ];

        return [
            'owner' => self::permissions(),
            'accountant' => $accountant,
            'approver' => [
                self::JOURNAL_APPROVE, self::JOURNAL_POST, self::INVOICE_POST, self::CREDITMEMO_POST, self::BILL_POST,
                self::PERIOD_MANAGE, self::PERIOD_CLOSE, self::ACCOUNT_MANAGE, self::TAX_VIEW, self::REPORTS_VIEW,
            ],
            'bookkeeper' => [
                self::JOURNAL_CREATE, self::JOURNAL_SUBMIT, self::INVOICE_MANAGE, self::CREDITMEMO_MANAGE,
                self::BILL_MANAGE, self::RECURRING_MANAGE, self::REPORTS_VIEW,
            ],
            'viewer' => [self::REPORTS_VIEW],
        ];
    }

    /**
     * The full permission catalog (deduped union of every role bundle plus the
     * owner-only cross-cutting permissions).
     *
     * @return list<string>
     */
    public static function permissions(): array
    {
        return [
            self::JOURNAL_CREATE, self::JOURNAL_SUBMIT, self::JOURNAL_APPROVE, self::JOURNAL_POST, self::JOURNAL_REVERSE,
            self::INVOICE_MANAGE, self::INVOICE_POST, self::INVOICE_VOID, self::CREDITMEMO_MANAGE, self::CREDITMEMO_POST, self::PAYMENT_RECEIVE,
            self::BILL_MANAGE, self::BILL_POST, self::BILL_PAY, self::PAYMENT_PAY,
            self::BANK_RECORD, self::BANK_RECONCILE,
            self::INVENTORY_ADJUST, self::ASSET_MANAGE, self::ASSET_DEPRECIATE, self::ASSET_DISPOSE,
            self::PERIOD_MANAGE, self::PERIOD_CLOSE, self::YEAR_CLOSE, self::RECURRING_MANAGE, self::RECURRING_RUN,
            self::ACCOUNT_MANAGE, self::TAX_VIEW, self::TAX_RETURNS_MANAGE,
            self::REPORTS_VIEW, self::AUDIT_VIEW, self::COMPANY_MANAGE, self::COMPANY_EXPORT, self::USERS_MANAGE,
        ];
    }

    /**
     * Idempotently create the global permissions and standard roles and wire
     * each role to its permission bundle. Safe to call repeatedly.
     */
    public static function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);

        foreach (self::permissions() as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        foreach (self::roles() as $name => $permissions) {
            $role = Role::findOrCreate($name, self::GUARD);
            $role->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }
}
