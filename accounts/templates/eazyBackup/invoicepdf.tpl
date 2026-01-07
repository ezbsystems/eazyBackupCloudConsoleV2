<?php
// ---- eazyBackup: PDF usage enrichment helpers (cron-safe) ----
if (!function_exists('ebFmtBytes')) {
    function ebFmtBytes(?int $bytes): string {
        if ($bytes === null) return 'N/A';
        $b = (int) $bytes;
        if ($b < 1024) return $b . ' B';
        $units = ['KB','MB','GB','TB','PB','EB'];
        $i = -1;
        do { $b /= 1024; ++$i; } while ($b >= 1024 && $i < count($units)-1);
        return sprintf('%.2f %s', $b, $units[$i]);
    }
}

if (!class_exists('EB_InvoiceUsage')) {
    class EB_InvoiceUsage {
        protected static array $cache = [];

        public static function getUsageForUsername(string $username, ?int $clientId = null): array
        {
            $key = $username . '|' . ($clientId ?? 0);
            if (isset(self::$cache[$key])) {
                return self::$cache[$key];
            }

            // Confirm username exists (CASE-SENSITIVE) in comet_users
            $existsQuery = \WHMCS\Database\Capsule::table('comet_users')
                ->when($clientId !== null, function ($q) use ($clientId) {
                    $q->where('user_id', $clientId);
                })
                ->whereRaw('BINARY `username` = ?', [$username]);

            $exists = (bool) $existsQuery->exists();

            if (!$exists) {
                return self::$cache[$key] = [
                    'username'    => $username,
                    'total_bytes' => null,
                    'vaults'      => [],
                    'devices'     => [],
                ];
            }

            // Vaults (active, not removed)
            $vaultRows = \WHMCS\Database\Capsule::table('comet_vaults')
                ->select(['name', 'total_bytes'])
                ->when($clientId !== null, function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                })
                ->whereRaw('BINARY `username` = ?', [$username])
                ->where('is_active', 1)
                ->whereNull('removed_at')
                ->orderBy('name')
                ->get();

            $vaults = [];
            $totalBytes = 0;
            foreach ($vaultRows as $r) {
                $bytes = is_null($r->total_bytes) ? 0 : (int) $r->total_bytes;
                $vaults[] = [
                    'name'        => (string) $r->name,
                    'total_bytes' => $bytes,
                ];
                $totalBytes += $bytes;
            }

            // Devices (active)
            $deviceRows = \WHMCS\Database\Capsule::table('comet_devices')
                ->select(['name', 'hash', 'platform_os', 'platform_arch', 'is_active'])
                ->when($clientId !== null, function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                })
                ->whereRaw('BINARY `username` = ?', [$username])
                ->where('is_active', 1)
                ->orderBy('name')
                ->get();

            $devices = [];
            foreach ($deviceRows as $d) {
                $devices[] = [
                    'name'          => (string) ($d->name ?? ''),
                    'hash'          => (string) ($d->hash ?? ''),
                    'platform_os'   => (string) ($d->platform_os ?? ''),
                    'platform_arch' => (string) ($d->platform_arch ?? ''),
                    'is_active'     => (int) $d->is_active,
                ];
            }

            return self::$cache[$key] = [
                'username'    => $username,
                'total_bytes' => $totalBytes,
                'vaults'      => $vaults,
                'devices'     => $devices,
            ];
        }
    }
}
// ---- /helpers ----

# use \WHMCS\Database\Capsule; // Not required when using fully-qualified names below
# Logo
$logoFilename = 'placeholder.png';
if (file_exists(ROOTDIR . '/assets/img/logo.png')) {
    $logoFilename = 'logo.png';
} elseif (file_exists(ROOTDIR . '/assets/img/logo.jpg')) {
    $logoFilename = 'logo.jpg';
}
$pdf->Image(ROOTDIR . '/assets/img/' . $logoFilename, 15, 25, 35);


# Company Details
$pdf->SetXY(15, 25);
$pdf->SetFont($pdfFont, '', 13);
foreach ($companyaddress as $addressLine) {
    $pdf->Cell(180, 4, trim($addressLine), 0, 1, 'R');
    $pdf->SetFont($pdfFont, '', 9);
}
if ($taxCode) {
    $pdf->Cell(180, 4, $taxIdLabel . ': ' . trim($taxCode), 0, 1, 'R');
}
$pdf->Ln(5);

# Header Bar

/**
 * Invoice header
 *
 * You can optionally define a header/footer in a way that is repeated across page breaks.
 * For more information, see http://docs.whmcs.com/PDF_Invoice#Header.2FFooter
 */

$pdf->SetFont($pdfFont, 'B', 15);
$pdf->SetFillColor(239);
$pdf->Cell(0, 8, $pagetitle, 0, 1, 'L', '1');
$pdf->SetFont($pdfFont, '', 10);
$pdf->Cell(0, 6, Lang::trans('invoicesdatecreated') . ': ' . $datecreated, 0, 1, 'L', '1');
$pdf->Cell(0, 6, Lang::trans('invoicesdatedue') . ': ' . $duedate, 0, 1, 'L', '1');
$pdf->Ln(10);

$startpage = $pdf->GetPage();

# Clients Details
$addressypos = $pdf->GetY();
$pdf->SetFont($pdfFont, 'B', 10);
$pdf->Cell(0, 4, Lang::trans('invoicesinvoicedto'), 0, 1);
$pdf->SetFont($pdfFont, '', 9);
if ($clientsdetails["companyname"]) {
    $pdf->Cell(0, 4, $clientsdetails["companyname"], 0, 1, 'L');
    $pdf->Cell(0, 4, Lang::trans('invoicesattn') . ': ' . $clientsdetails["firstname"] . ' ' . $clientsdetails["lastname"], 0, 1, 'L');
} else {
    $pdf->Cell(0, 4, $clientsdetails["firstname"] . " " . $clientsdetails["lastname"], 0, 1, 'L');
}
$pdf->Cell(0, 4, $clientsdetails["address1"], 0, 1, 'L');
if ($clientsdetails["address2"]) {
    $pdf->Cell(0, 4, $clientsdetails["address2"], 0, 1, 'L');
}
$pdf->Cell(0, 4, $clientsdetails["city"] . ", " . $clientsdetails["state"] . ", " . $clientsdetails["postcode"], 0, 1, 'L');
$pdf->Cell(0, 4, $clientsdetails["country"], 0, 1, 'L');
if (array_key_exists('tax_id', $clientsdetails) && $clientsdetails['tax_id']) {
    $pdf->Cell(0, 4, $taxIdLabel . ': ' . $clientsdetails['tax_id'], 0, 1, 'L');
}
if ($customfields) {
    $pdf->Ln();
    foreach ($customfields as $customfield) {
        $pdf->Cell(0, 4, $customfield['fieldname'] . ': ' . $customfield['value'], 0, 1, 'L');
    }
}
$pdf->Ln(10);

# Invoice Items
$tblhtml = '<table width="100%" bgcolor="#ccc" cellspacing="1" cellpadding="2" border="0">
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;text-align:center;">
        <td width="80%">' . Lang::trans('invoicesdescription') . '</td>
        <td width="20%">' . Lang::trans('quotelinetotal') . '</td>
    </tr>';
foreach ($invoiceitems as $item) {

    // Clean up description like original logic (do BEFORE adding Username/usage)
    if (isset($item['description']) && is_string($item['description'])) {
        $text = preg_replace("/\:\s0\sx\s/","+",$item['description']);
        $text = preg_replace("/.*\+/","+",$text);
        $text = preg_replace("/\+.*(\n?)/","",$text);
        $item['description'] = $text;
    } else {
        $item['description'] = strval(isset($item['description']) ? $item['description'] : '');
    }

    // ---- eazyBackup: enrich invoice line with Username + usage ----
    $serviceIdInt = null;
    if (isset($item['type']) && $item['type'] === 'Upgrade' && isset($item['relid']) && ctype_digit((string)$item['relid'])) {
        $upgradeIdInt = (int)$item['relid'];
        $serviceIdInt = \WHMCS\Database\Capsule::table('tblupgrades')->where('id', $upgradeIdInt)->where('type', 'configoptions')->value('relid');
        $serviceIdInt = $serviceIdInt ? (int)$serviceIdInt : null;
    } elseif (isset($item['relid']) && ctype_digit((string)$item['relid'])) {
        $serviceIdInt = (int)$item['relid'];
    }

    $resolvedUsername = null;
    $clientId = null;
    if ($serviceIdInt) {
        // Optional guard: only read usernames for the invoice's client when available
        $invoiceClientId = 0;
        if (isset($clientsdetails) && is_array($clientsdetails)) {
            $invoiceClientId = (int)($clientsdetails['userid'] ?? $clientsdetails['id'] ?? 0);
        }
        $q = \WHMCS\Database\Capsule::table('tblhosting')->select(['username','userid'])->where('id', $serviceIdInt);
        if ($invoiceClientId > 0) {
            $q->where('userid', $invoiceClientId);
        }
        $row = $q->first();
        if ($row) {
            $resolvedUsername = (string) $row->username;
            $clientId = (int) $row->userid;
        }
    }

    if (is_string($resolvedUsername) && $resolvedUsername !== '') {
        $item['description'] .= "\nUsername: " . $resolvedUsername;

        if (class_exists('EB_InvoiceUsage')) {
            $usage = EB_InvoiceUsage::getUsageForUsername($resolvedUsername, $clientId);

            if (isset($usage['total_bytes']) && $usage['total_bytes'] !== null) {
                $item['description'] .= "\nStorage used: " . ebFmtBytes($usage['total_bytes']);
            }
            if (!empty($usage['vaults'])) {
                $item['description'] .= "\nStorage vaults:";
                foreach ($usage['vaults'] as $v) {
                    $vName  = $v['name'] ?: '(unnamed vault)';
                    $vBytes = ebFmtBytes($v['total_bytes'] ?? 0);
                    $item['description'] .= "\n  • {$vName} — {$vBytes}";
                }
            }
            if (!empty($usage['devices'])) {
                $item['description'] .= "\nDevices:";
                foreach ($usage['devices'] as $d) {
                    $name = $d['name'] ?: $d['hash'];
                    $os   = trim(($d['platform_os'] ?? '') . ' ' . ($d['platform_arch'] ?? ''));
                    $line = "  • " . $name . ($os ? " ({$os})" : "");
                    $item['description'] .= "\n" . $line;
                }
            }
        }
    }
    // ---- /enrichment ----

    $tblhtml .= '
    <tr bgcolor="#fff">
        <td align="left">' . nl2br($item['description']) . '</td>
        <td align="center">' . $item['amount'] . '</td>
    </tr>';
}
$tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . Lang::trans('invoicessubtotal') . '</td>
        <td align="center">' . $subtotal . '</td>
    </tr>';
if ($taxname) {
    $tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . $taxrate . '% ' . $taxname . '</td>
        <td align="center">' . $tax . '</td>
    </tr>';
}
if ($taxname2) {
    $tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . $taxrate2 . '% ' . $taxname2 . '</td>
        <td align="center">' . $tax2 . '</td>
    </tr>';
}
$tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . Lang::trans('invoicescredit') . '</td>
        <td align="center">' . $credit . '</td>
    </tr>
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td align="right">' . Lang::trans('invoicestotal') . '</td>
        <td align="center">' . $total . '</td>
    </tr>
</table>';

$pdf->writeHTML($tblhtml, true, false, false, false, '');

$pdf->Ln(5);

# Transactions
$pdf->SetFont($pdfFont, 'B', 12);
$pdf->Cell(0, 4, Lang::trans('invoicestransactions'), 0, 1);

$pdf->Ln(5);

$pdf->SetFont($pdfFont, '', 9);

$tblhtml = '<table width="100%" bgcolor="#ccc" cellspacing="1" cellpadding="2" border="0">
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;text-align:center;">
        <td width="25%">' . Lang::trans('invoicestransdate') . '</td>
        <td width="25%">' . Lang::trans('invoicestransgateway') . '</td>
        <td width="30%">' . Lang::trans('invoicestransid') . '</td>
        <td width="20%">' . Lang::trans('invoicestransamount') . '</td>
    </tr>';

if (!count($transactions)) {
    $tblhtml .= '
    <tr bgcolor="#fff">
        <td colspan="4" align="center">' . Lang::trans('invoicestransnonefound') . '</td>
    </tr>';
} else {
    foreach ($transactions AS $trans) {
        $tblhtml .= '
        <tr bgcolor="#fff">
            <td align="center">' . $trans['date'] . '</td>
            <td align="center">' . $trans['gateway'] . '</td>
            <td align="center">' . $trans['transid'] . '</td>
            <td align="center">' . $trans['amount'] . '</td>
        </tr>';
    }
}
$tblhtml .= '
    <tr height="30" bgcolor="#efefef" style="font-weight:bold;">
        <td colspan="3" align="right">' . Lang::trans('invoicesbalance') . '</td>
        <td align="center">' . $balance . '</td>
    </tr>
</table>';

$pdf->writeHTML($tblhtml, true, false, false, false, '');

# Custom text for notes
$customText = "For annual plans, if your usage exceeds the allocated limits at any point during the year, we will issue a prorated invoice to cover the additional usage. To avoid additional charges, you can set limits on Storage and Devices through your client area or by contacting us.";

# Notes
if ($notes) {
    $notes .= "\n" . $customText; // Append custom text to existing notes
    $pdf->Ln(5);
    $pdf->SetFont($pdfFont, '', 8);
    $pdf->MultiCell(170, 5, Lang::trans('invoicesnotes') . ': ' . $notes, 0, 'L');
} else {
    // If there are no existing notes, just print the custom text
    $pdf->Ln(5);
    $pdf->SetFont($pdfFont, '', 8);
    $pdf->MultiCell(170, 5, Lang::trans('invoicesnotes') . ': ' . $customText);
}
/**
 * Invoice footer
 */
