<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Task 1 of Phase 3.3: Ensure preferences table exists with audit columns.
 *
 * The preferences table has existed since very early in the application and
 * is used for all competition-wide settings (email, payment, judging rules,
 * entry limits, display preferences, etc.). Phase 3.3 extends it with audit
 * columns to track who changed what and when.
 *
 * This migration:
 * 1. If the preferences table doesn't exist (fresh install), creates it with all
 *    known columns PLUS the two new audit columns (changedAt, changedBy).
 * 2. If it already exists (all live/existing installs), ALTERs it to ADD the two
 *    audit columns (if not already present) without touching existing data.
 *
 * The preferences table is single-row by design (id=1 is the canonical row);
 * this is enforced by application code, not by a constraint at the DB level.
 */
final class EnsurePreferencesAudit extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('preferences', ['id' => 'id', 'signed' => false]);

        // Only add columns if the table doesn't already exist
        // Phinx will detect an existing table and skip the column additions
        // if they're already present (hasColumn checks are done internally by the adapter)
        if (!$this->hasTable('preferences')) {
            // Fresh install: create the table with all known columns + audit columns
            $table
                ->addColumn('prefsEmailSMTP', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Enable SMTP email (0/1)'])
                ->addColumn('prefsEmailHost', 'string', ['null' => true, 'limit' => 255, 'comment' => 'SMTP host'])
                ->addColumn('prefsEmailFrom', 'string', ['null' => true, 'limit' => 255, 'comment' => 'From email address'])
                ->addColumn('prefsEmailUsername', 'string', ['null' => true, 'limit' => 255, 'comment' => 'SMTP username'])
                ->addColumn('prefsEmailPassword', 'string', ['null' => true, 'limit' => 255, 'comment' => 'SMTP password'])
                ->addColumn('prefsEmailEncrypt', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Encryption method (e.g. TLS/SSL)'])
                ->addColumn('prefsEmailPort', 'string', ['null' => true, 'limit' => 255, 'comment' => 'SMTP port'])
                ->addColumn('prefsPaypal', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Enable PayPal (Y/N)'])
                ->addColumn('prefsPaypalAccount', 'string', ['null' => true, 'limit' => 255, 'comment' => 'PayPal account email'])
                ->addColumn('prefsPaypalIPN', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'PayPal IPN enabled (0/1)'])
                ->addColumn('prefsCurrency', 'string', ['null' => true, 'limit' => 20, 'comment' => 'Currency symbol (e.g. $)'])
                ->addColumn('prefsCash', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Accept cash payments (Y/N)'])
                ->addColumn('prefsCheck', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Accept check payments (Y/N)'])
                ->addColumn('prefsCheckPayee', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Payee name for checks'])
                ->addColumn('prefsTransFee', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Charge transaction fee (Y/N)'])
                ->addColumn('prefsCAPTCHA', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Enable CAPTCHA (0/1)'])
                ->addColumn('prefsGoogleAccount', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Google Merchant ID'])
                ->addColumn('prefsSponsors', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Display sponsors (Y/N)'])
                ->addColumn('prefsSponsorLogos', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Display sponsor logos (Y/N)'])
                ->addColumn('prefsSelectedStyles', 'text', ['null' => true, 'comment' => 'JSON array of selected beer styles'])
                ->addColumn('prefsCompLogoSize', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Logo size setting'])
                ->addColumn('prefsDisplayWinners', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Display winners (Y/N)'])
                ->addColumn('prefsWinnerDelay', 'string', ['null' => true, 'limit' => 15, 'comment' => 'Unix timestamp to display winners'])
                ->addColumn('prefsWinnerMethod', 'integer', ['null' => true, 'comment' => 'Winner selection method: 0=table, 1=category, 2=sub-category'])
                ->addColumn('prefsDisplaySpecial', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Display special categories (Y/N)'])
                ->addColumn('prefsBOSMead', 'char', ['null' => true, 'limit' => 1, 'default' => 'N', 'comment' => 'Best of Show for Mead (Y/N)'])
                ->addColumn('prefsBOSCider', 'char', ['null' => true, 'limit' => 1, 'default' => 'N', 'comment' => 'Best of Show for Cider (Y/N)'])
                ->addColumn('prefsEntryForm', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Show entry form (Y/N)'])
                ->addColumn('prefsRecordLimit', 'integer', ['null' => true, 'default' => 500, 'comment' => 'Record limit for DataTables vs PHP paging'])
                ->addColumn('prefsRecordPaging', 'integer', ['null' => true, 'default' => 30, 'comment' => 'Records per page'])
                ->addColumn('prefsProEdition', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Pro edition enabled (0/1)'])
                ->addColumn('prefsTheme', 'string', ['null' => true, 'limit' => 255, 'comment' => 'UI theme'])
                ->addColumn('prefsDateFormat', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Date format preference'])
                ->addColumn('prefsContact', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Show contact info (Y/N)'])
                ->addColumn('prefsTimeZone', 'float', ['null' => true, 'comment' => 'Timezone offset'])
                ->addColumn('prefsEntryLimit', 'integer', ['null' => true, 'comment' => 'Maximum entries for competition'])
                ->addColumn('prefsTimeFormat', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Time format preference'])
                ->addColumn('prefsUserEntryLimit', 'string', ['null' => true, 'limit' => 4, 'comment' => 'Max entries per user'])
                ->addColumn('prefsUserSubCatLimit', 'string', ['null' => true, 'limit' => 4, 'comment' => 'Max entries per user per sub-category'])
                ->addColumn('prefsUSCLEx', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Sub-category limit exceptions'])
                ->addColumn('prefsUSCLExLimit', 'string', ['null' => true, 'limit' => 4, 'comment' => 'Exception entry limit'])
                ->addColumn('prefsUserEntryLimitDates', 'text', ['null' => true, 'comment' => 'Date range for entry limits'])
                ->addColumn('prefsPayToPrint', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Require payment before printing (Y/N)'])
                ->addColumn('prefsHideRecipe', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Hide recipe sections (Y/N)'])
                ->addColumn('prefsUseMods', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Enable custom modules (Y/N)'])
                ->addColumn('prefsSEF', 'char', ['null' => true, 'limit' => 1, 'comment' => 'Use search engine friendly URLs (Y/N)'])
                ->addColumn('prefsSpecialCharLimit', 'integer', ['null' => true, 'limit' => 3, 'comment' => 'Special character limit'])
                ->addColumn('prefsStyleSet', 'text', ['null' => true, 'comment' => 'Beer style set (e.g. BJCP2021)'])
                ->addColumn('prefsAutoPurge', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Auto-purge old data (0/1)'])
                ->addColumn('prefsEntryLimitPaid', 'integer', ['null' => true, 'limit' => 4, 'comment' => 'Entry limit for paid entries'])
                ->addColumn('prefsEmailRegConfirm', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Email registration confirmation (0/1)'])
                ->addColumn('prefsEmailCC', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'CC admin on emails (0/1)'])
                ->addColumn('prefsShipping', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Track shipping (0/1)'])
                ->addColumn('prefsDropOff', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Allow drop-off (0/1)'])
                ->addColumn('prefsLanguage', 'string', ['null' => true, 'limit' => 25, 'comment' => 'Language preference'])
                ->addColumn('prefsSpecific', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Specific setting (0/1)'])
                ->addColumn('prefsShowBestBrewer', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Show Best Brewer award (0/1)'])
                ->addColumn('prefsBestBrewerTitle', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Best Brewer title'])
                ->addColumn('prefsFirstPlacePts', 'integer', ['null' => true, 'limit' => 1, 'default' => 0, 'comment' => 'Points for 1st place'])
                ->addColumn('prefsSecondPlacePts', 'integer', ['null' => true, 'limit' => 1, 'default' => 0, 'comment' => 'Points for 2nd place'])
                ->addColumn('prefsThirdPlacePts', 'integer', ['null' => true, 'limit' => 1, 'default' => 0, 'comment' => 'Points for 3rd place'])
                ->addColumn('prefsFourthPlacePts', 'integer', ['null' => true, 'limit' => 1, 'default' => 0, 'comment' => 'Points for 4th place'])
                ->addColumn('prefsHMPts', 'integer', ['null' => true, 'limit' => 1, 'default' => 0, 'comment' => 'Points for Honorable Mention'])
                ->addColumn('prefsTieBreakRule1', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Tie-break rule 1'])
                ->addColumn('prefsTieBreakRule2', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Tie-break rule 2'])
                ->addColumn('prefsTieBreakRule3', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Tie-break rule 3'])
                ->addColumn('prefsTieBreakRule4', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Tie-break rule 4'])
                ->addColumn('prefsTieBreakRule5', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Tie-break rule 5'])
                ->addColumn('prefsTieBreakRule6', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Tie-break rule 6'])
                ->addColumn('prefsShowBestClub', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Show Best Club award (0/1)'])
                ->addColumn('prefsBestClubTitle', 'string', ['null' => true, 'limit' => 255, 'comment' => 'Best Club title'])
                ->addColumn('prefsBestUseBOS', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Use BOS for Best Club (0/1)'])
                ->addColumn('prefsEval', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Evaluation enabled (0/1)'])
                ->addColumn('prefsScoringCOA', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Use COA for scoring (0/1)'])
                ->addColumn('prefsMHPDisplay', 'integer', ['null' => true, 'limit' => 1, 'comment' => 'Display MHP data (0/1)'])
                ->addColumn('prefsStyleLimits', 'text', ['null' => true, 'comment' => 'JSON array of entry limits for selected style set'])
                // Audit columns
                ->addColumn('changedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false, 'comment' => 'Timestamp of last change'])
                ->addColumn('changedBy', 'integer', ['null' => true, 'signed' => false, 'comment' => 'FK to users.id; user who made the change; null for system changes'])
                ->create();
        } else {
            // Existing install: just add the audit columns if they don't exist
            // Check if changedAt exists; if not, add both audit columns
            if (!$this->table('preferences')->hasColumn('changedAt')) {
                $table
                    ->addColumn('changedAt', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => false, 'comment' => 'Timestamp of last change'])
                    ->addColumn('changedBy', 'integer', ['null' => true, 'signed' => false, 'comment' => 'FK to users.id; user who made the change; null for system changes'])
                    ->update();
            }
        }
    }
}
