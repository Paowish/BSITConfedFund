<?php
require_once __DIR__ . '/config.php';

function formatPeso($amount) {
    return '₱' . number_format($amount, 2);
}

function getFinancialSummary($pdo) {
    $stmt = $pdo->query("SELECT SUM(amount) AS total FROM funds WHERE status='approved'");
    $totalFunds = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT SUM(amount) AS total FROM expenses WHERE status='approved'");
    $totalExpenses = $stmt->fetchColumn();

    return [
        'total_funds' => $totalFunds ?? 0,
        'total_expenses' => $totalExpenses ?? 0,
        'balance' => ($totalFunds ?? 0) - ($totalExpenses ?? 0)
    ];
}

/* =====================================================
   AVATAR HELPERS — for profile picture feature
   ===================================================== */

if (!function_exists('getInitials')) {
    /**
     * Get initials from a full name.
     * Example: "John Marlou Castillo" → "JC"
     */
    function getInitials($name) {
        $name = trim($name ?? '');
        if ($name === '') return '?';

        $parts = preg_split('/\s+/', $name);
        $parts = array_filter($parts);

        if (count($parts) >= 2) {
            $first = mb_substr($parts[0], 0, 1, 'UTF-8');
            $last  = mb_substr(end($parts), 0, 1, 'UTF-8');
            return mb_strtoupper($first . $last, 'UTF-8');
        }
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
}

if (!function_exists('getAvatarColor')) {
    /**
     * Get a consistent color based on name hash.
     * Same name always gets same color.
     */
    function getAvatarColor($name) {
        $colors = [
            '#2980b9', '#e74c3c', '#27ae60', '#8e44ad',
            '#f39c12', '#16a085', '#d35400', '#c0392b',
            '#2c3e50', '#1abc9c', '#e67e22', '#34495e'
        ];
        $hash = crc32(strtolower(trim($name ?? '')));
        return $colors[abs($hash) % count($colors)];
    }
}

if (!function_exists('getCurrentUserProfilePic')) {
    /**
     * Returns the profile_picture path of the logged-in user, or null if none.
     */
    function getCurrentUserProfilePic($pdo) {
        if (isset($_SESSION['officer_id'])) {
            $stmt = $pdo->prepare("SELECT profile_picture FROM officers WHERE id = ?");
            $stmt->execute([$_SESSION['officer_id']]);
            return $stmt->fetchColumn() ?: null;
        }
        if (isset($_SESSION['adviser_id'])) {
            $stmt = $pdo->prepare("SELECT profile_picture FROM adviser_account WHERE id = ?");
            $stmt->execute([$_SESSION['adviser_id']]);
            return $stmt->fetchColumn() ?: null;
        }
        return null;
    }
}

if (!function_exists('renderAvatar')) {
    /**
     * Render avatar — profile picture if available, initials fallback if not.
     *
     * @param string $name       Full name
     * @param string|null $picPath   Relative path (e.g., "uploads/profiles/xxx.jpg")
     * @param int $size          Pixel size (default 38)
     */
    function renderAvatar($name, $picPath = null, $size = 38) {
        $size = (int)$size;

        if (!empty($picPath)) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/' . ltrim($picPath, '/');
            if (file_exists($fullPath)) {
                $src = '/bsit-fund-system-v2/' . ltrim($picPath, '/');
                return '<img src="' . htmlspecialchars($src) . '" alt="Avatar" '
                     . 'class="user-avatar user-avatar-img" '
                     . 'style="width:' . $size . 'px;height:' . $size . 'px;">';
            }
        }

                $initials = getInitials($name);
        // Gamitin ang dynamic theme color
        $color = 'var(--theme-primary, #1a3c5e)'; 
        
        return '<div class="user-avatar user-avatar-initials" '
             . 'style="width:' . $size . 'px;height:' . $size . 'px;background:' . $color . ';font-size:'
             . round($size * 0.42) . 'px;">' . htmlspecialchars($initials) . '</div>';
    }
}


/* =====================================================
   SYSTEM SETTINGS HELPER
   ===================================================== */

if (!function_exists('getSettings')) {
    function getSettings($pdo) {
        $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
        $settings = $stmt->fetch();
        
        if (!$settings) {
            return [
                'org_name' => 'BSIT Confederation',
                'org_short_name' => 'BSIT Confed Fund',
                'logo_path' => 'assets/images/logo.png',
                'primary_color' => '#1a3c5e'
            ];
        }
        
        return $settings;
    }
}

if (!function_exists('hexToRgb')) {
    /**
     * Convert HEX color to RGB array for FPDF
     */
    function hexToRgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }
}

/* =====================================================
   STATUS BADGE HELPER
   ===================================================== */
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        switch($status) {
            case 'pending_audit':
                return '<span style="background:#fff3cd; color:#856404; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700; white-space:nowrap;"><i class="fas fa-clock"></i> Pending Audit</span>';
            case 'audited':
            case 'pending_adviser_review':
            case 'audited_compliant':
            case 'audited_with_findings':
                return '<span style="background:#cce5ff; color:#004085; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700; white-space:nowrap;"><i class="fas fa-check-circle"></i> Pending Final Approval</span>';
            case 'approved':
            case 'closed':
                return '<span style="background:#d4edda; color:#155724; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700; white-space:nowrap;"><i class="fas fa-check-double"></i> Approved</span>';
            case 'denied':
                return '<span style="background:#f8d7da; color:#721c24; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700; white-space:nowrap;"><i class="fas fa-times-circle"></i> Denied</span>';
            case 'returned_for_revision':
                return '<span style="background:#f8d7da; color:#721c24; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700; white-space:nowrap;"><i class="fas fa-undo"></i> Returned for Revision</span>';
            case 'draft':
                return '<span style="background:#e2e8f0; color:#475569; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700; white-space:nowrap;"><i class="fas fa-file"></i> Draft</span>';
            case 'pending_treasurer_review':
                return '<span style="background:#cce5ff; color:#004085; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700; white-space:nowrap;"><i class="fas fa-clock"></i> Pending Review</span>';
            default:
                return '<span style="background:#e2e8f0; color:#475569; padding:4px 10px; border-radius:50px; font-size:11px; font-weight:700;">' . ucwords(str_replace('_', ' ', $status)) . '</span>';
        }
    }
}
?>