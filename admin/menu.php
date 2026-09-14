<?php
declare(strict_types=1);

require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();
$csrfToken = getCsrfToken();

// Handle AJAX toggle request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajax_toggle') {
    header('Content-Type: application/json; charset=utf-8');
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => __('invalid_csrf_token')]);
        exit;
    }
    
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $isActive = isset($_POST['is_active']) && (int)$_POST['is_active'] === 1 ? 1 : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        exit;
    }
    
    try {
        $stmt = $db->prepare("UPDATE menu_items SET is_active = ? WHERE id = ?");
        $stmt->execute([$isActive, $id]);
        echo json_encode([
            'success' => true,
            'is_active' => $isActive,
            'message' => __('toggle_status_success')
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle standard POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', __('invalid_csrf_token'));
        header("Location: menu.php");
        exit;
    }

    if (isset($_POST['add_item'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priceRaw = $_POST['price'] ?? '';
        $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT);
        if ($sortOrder === false || $sortOrder === null) {
            $stmt = $db->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM menu_items");
            $sortOrder = (int)$stmt->fetchColumn();
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            setFlash('error', __('name_required'));
            header("Location: menu.php");
            exit;
        }

        if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
            setFlash('error', __('invalid_price'));
            header("Location: menu.php");
            exit;
        }

        $price = (float)$priceRaw;

        try {
            $imagePath = handleImageUpload('image_file');
            $stmt = $db->prepare("INSERT INTO menu_items (name, description, price, image_path, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $imagePath, $isActive, $sortOrder]);
            setFlash('success', __('item_added_success'));
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    } elseif (isset($_POST['update_item'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $priceRaw = $_POST['price'] ?? '';
        $sortOrder = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT);
        if ($sortOrder === false || $sortOrder === null) {
            $sortOrder = 0;
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $existingImagePath = $_POST['image_path'] ?? null;

        if (!$id) {
            setFlash('error', 'Invalid menu item ID.');
            header("Location: menu.php");
            exit;
        }

        if ($name === '') {
            setFlash('error', __('name_required'));
            header("Location: menu.php");
            exit;
        }

        if (!is_numeric($priceRaw) || (float)$priceRaw < 0) {
            setFlash('error', __('invalid_price'));
            header("Location: menu.php");
            exit;
        }

        $price = (float)$priceRaw;

        try {
            $imagePath = handleImageUpload('image_file', $existingImagePath);
            $stmt = $db->prepare("UPDATE menu_items SET name=?, description=?, price=?, image_path=?, is_active=?, sort_order=? WHERE id=?");
            $stmt->execute([$name, $description, $price, $imagePath, $isActive, $sortOrder, $id]);
            setFlash('success', __('item_updated_success'));
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    } elseif (isset($_POST['delete_item'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id) {
            setFlash('error', 'Invalid menu item ID.');
            header("Location: menu.php");
            exit;
        }

        try {
            // Check if item has order history in order_items
            $stmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE menu_item_id = ?");
            $stmt->execute([$id]);
            $orderCount = (int)$stmt->fetchColumn();

            if ($orderCount > 0) {
                // Soft deactivate to preserve accounting and past order history
                $stmt = $db->prepare("UPDATE menu_items SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('warning', __('cannot_delete_item_has_orders'));
            } else {
                // Retrieve existing image path to delete file from disk
                $stmt = $db->prepare("SELECT image_path FROM menu_items WHERE id = ?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();

                $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
                $stmt->execute([$id]);

                if (!empty($item['image_path'])) {
                    $fullPath = ROOT_PATH . DIRECTORY_SEPARATOR . $item['image_path'];
                    if (file_exists($fullPath) && is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                setFlash('success', __('item_deleted_success'));
            }
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }

    header("Location: menu.php");
    exit;
}

// Fetch all menu items ordered by sort_order ASC, name ASC
$stmt = $db->query("SELECT * FROM menu_items ORDER BY sort_order ASC, name ASC");
$menu_items = $stmt->fetchAll();

// Flash message
$flash = getFlash();

// Stats calculation
$totalCount = count($menu_items);
$activeCount = 0;
foreach ($menu_items as $item) {
    if (!empty($item['is_active'])) {
        $activeCount++;
    }
}
$hiddenCount = $totalCount - $activeCount;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string)$_SESSION['lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🍕 <?= htmlspecialchars(__('admin_menu_management')) ?> - <?= htmlspecialchars($config['site_title'] ?? "Fidel's Pizza Event") ?></title>
    <style>
        :root {
            --primary: #2c3e50;
            --primary-dark: #1a252f;
            --blue: #3498db;
            --blue-hover: #2980b9;
            --green: #27ae60;
            --green-hover: #219653;
            --red: #e74c3c;
            --red-hover: #c0392b;
            --gray-100: #f8fafc;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.12);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f1f5f9;
            color: var(--gray-800);
            line-height: 1.5;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: var(--primary);
            color: white;
            padding: 16px 0;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: var(--radius-md);
            font-size: 0.92rem;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-links a:hover, .nav-links a.active {
            background: rgba(255,255,255,0.18);
        }
        .lang-selector select {
            padding: 6px 10px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.15);
            color: white;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .lang-selector select option {
            background: var(--primary);
            color: white;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 28px auto;
            padding: 0 20px 60px;
        }

        /* Flash Alerts */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.95rem;
            box-shadow: var(--shadow-sm);
            animation: fadeIn 0.3s ease-in-out;
        }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid var(--green); }
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--red); }
        .alert-close {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            color: inherit;
            opacity: 0.6;
        }
        .alert-close:hover { opacity: 1; }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }
        .page-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .page-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            font-size: 0.85rem;
            color: var(--gray-700);
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-primary { background: var(--blue); color: white; }
        .btn-primary:hover { background: var(--blue-hover); transform: translateY(-1px); }
        .btn-success { background: var(--green); color: white; }
        .btn-success:hover { background: var(--green-hover); }
        .btn-danger { background: #fff; color: var(--red); border: 1px solid #fca5a5; }
        .btn-danger:hover { background: #fef2f2; border-color: var(--red); }
        .btn-outline { background: white; color: var(--gray-700); border: 1px solid var(--gray-300); }
        .btn-outline:hover { background: var(--gray-100); border-color: var(--gray-500); }
        .btn-sm { padding: 6px 12px; font-size: 0.85rem; }

        /* Filter & Search Bar */
        .filter-bar {
            background: white;
            padding: 14px 20px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        .search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
            max-width: 380px;
        }
        .search-box input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-box input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }
        .search-icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
            pointer-events: none;
        }
        .filter-tabs {
            display: flex;
            gap: 6px;
        }
        .tab-btn {
            padding: 7px 14px;
            background: var(--gray-100);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Menu Grid */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        /* Menu Card */
        .menu-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }
        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .menu-card.is-hidden {
            opacity: 0.72;
            border-style: dashed;
        }

        /* Card Image */
        .card-image-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            background: #f1f5f9;
            overflow: hidden;
        }
        .card-image-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }
        .menu-card:hover .card-image-wrap img {
            transform: scale(1.03);
        }
        .no-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            font-size: 2.5rem;
            background: #e2e8f0;
        }
        .no-image-placeholder span {
            font-size: 0.85rem;
            margin-top: 6px;
        }

        /* Badges */
        .badge-order {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            color: white;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .badge-status {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            backdrop-filter: blur(4px);
            transition: all 0.2s;
            user-select: none;
        }
        .badge-status.active {
            background: rgba(39, 174, 96, 0.92);
            color: white;
            box-shadow: 0 2px 6px rgba(39, 174, 96, 0.3);
        }
        .badge-status.hidden {
            background: rgba(100, 116, 139, 0.9);
            color: white;
        }

        /* Card Content */
        .card-body {
            padding: 18px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .card-title-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 8px;
        }
        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.3;
        }
        .card-price {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--blue);
            white-space: nowrap;
        }
        .card-description {
            color: var(--gray-500);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 14px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Card Footer */
        .card-footer {
            padding: 12px 20px;
            background: var(--gray-100);
            border-top: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .card-toggle-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        .card-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Switch Toggle Component */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--gray-300);
            transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        input:checked + .slider { background-color: var(--green); }
        input:checked + .slider:before { transform: translateX(20px); }

        /* Empty State */
        .empty-state {
            grid-column: 1 / -1;
            background: white;
            border-radius: var(--radius-lg);
            padding: 60px 20px;
            text-align: center;
            border: 2px dashed var(--gray-300);
        }
        .empty-state-icon { font-size: 3rem; margin-bottom: 12px; }
        .empty-state h3 { font-size: 1.25rem; color: var(--primary); margin-bottom: 6px; }
        .empty-state p { color: var(--gray-500); font-size: 0.95rem; margin-bottom: 20px; }

        /* Modals */
        .modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .modal-backdrop.is-open {
            opacity: 1;
            visibility: visible;
        }
        .modal-dialog {
            background: white;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 540px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            transform: scale(0.95);
            transition: transform 0.25s ease;
        }
        .modal-backdrop.is-open .modal-dialog {
            transform: scale(1);
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-100);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }
        .modal-header h3 {
            font-size: 1.2rem;
            color: var(--primary);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            color: var(--gray-500);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }
        .modal-close:hover { color: var(--gray-800); background: var(--gray-200); }

        .modal-body {
            padding: 22px 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 75px;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }
        .form-help {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* Image Upload Box & Instant Preview */
        .upload-container {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-md);
            padding: 16px;
            text-align: center;
            background: var(--gray-100);
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }
        .upload-container:hover {
            border-color: var(--blue);
            background: #f0f7ff;
        }
        .upload-container input[type="file"] {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .upload-prompt {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        .upload-prompt-icon { font-size: 1.7rem; color: var(--blue); }
        .image-preview-box {
            margin-top: 10px;
            position: relative;
            display: inline-block;
            max-width: 100%;
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--gray-300);
        }
        .image-preview-box img {
            max-height: 140px;
            width: auto;
            max-width: 100%;
            display: block;
            object-fit: cover;
        }

        .modal-footer {
            padding: 16px 24px;
            background: var(--gray-100);
            border-top: 1px solid var(--gray-200);
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast {
            background: var(--primary-dark);
            color: white;
            padding: 12px 18px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 14px; align-items: flex-start; }
            .nav-links { flex-wrap: wrap; width: 100%; }
            .nav-links a { padding: 6px 10px; font-size: 0.85rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            .form-row { grid-template-columns: 1fr; }
            .menu-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <div class="header">
        <div class="header-content">
            <h1>🍕 <?= htmlspecialchars(__('admin_menu_management')) ?></h1>
            <div class="nav-links">
                <a href="dashboard.php">📊 <?= htmlspecialchars(__('admin_dashboard')) ?></a>
                <a href="orders.php">📋 <?= htmlspecialchars(__('order_management')) ?></a>
                <a href="users.php">👥 <?= htmlspecialchars(__('user_management')) ?></a>
                <a href="menu.php" class="active">🍕 <?= htmlspecialchars(__('admin_menu_management')) ?></a>
                <a href="settings.php">⚙️ <?= htmlspecialchars(__('admin_settings')) ?></a>
                <a href="../logout.php">🚪 <?= htmlspecialchars(__('logout')) ?></a>
                <div class="lang-selector">
                    <form method="GET" action="">
                        <select name="lang" onchange="this.form.submit()">
                            <option value="ja" <?= $_SESSION['lang'] === 'ja' ? 'selected' : '' ?>>🇯🇵 日本語</option>
                            <option value="en" <?= $_SESSION['lang'] === 'en' ? 'selected' : '' ?>>🇺🇸 English</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">

        <!-- Flash Messages -->
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" id="flashAlert">
                <span><?= htmlspecialchars($flash['message']) ?></span>
                <button type="button" class="alert-close" onclick="document.getElementById('flashAlert').remove()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Page Header & Actions -->
        <div class="page-header">
            <div class="page-title-group">
                <h2 class="page-title"><?= htmlspecialchars(__('menu_items_list')) ?></h2>
                <span class="stat-badge">
                    <span>🍕</span> <span id="totalBadge"><?= $totalCount ?></span>
                </span>
                <span class="stat-badge" style="color: var(--green);">
                    <span>🟢</span> <span id="activeBadge"><?= $activeCount ?> <?= htmlspecialchars(__('status_active')) ?></span>
                </span>
                <span class="stat-badge" style="color: var(--gray-500);">
                    <span>⚪</span> <span id="hiddenBadge"><?= $hiddenCount ?> <?= htmlspecialchars(__('status_hidden')) ?></span>
                </span>
            </div>
            <div>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    ➕ <?= htmlspecialchars(__('add_new_item')) ?>
                </button>
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <div class="filter-bar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="<?= htmlspecialchars(__('search_menu')) ?>" oninput="applyFilters()">
            </div>
            <div class="filter-tabs">
                <button type="button" class="tab-btn active" data-filter="all" onclick="setFilter('all', this)">
                    <?= htmlspecialchars(__('filter_all')) ?> (<span id="tabCountAll"><?= $totalCount ?></span>)
                </button>
                <button type="button" class="tab-btn" data-filter="active" onclick="setFilter('active', this)">
                    <?= htmlspecialchars(__('status_active')) ?> (<span id="tabCountActive"><?= $activeCount ?></span>)
                </button>
                <button type="button" class="tab-btn" data-filter="hidden" onclick="setFilter('hidden', this)">
                    <?= htmlspecialchars(__('status_hidden')) ?> (<span id="tabCountHidden"><?= $hiddenCount ?></span>)
                </button>
            </div>
        </div>

        <!-- Menu Cards Grid -->
        <div class="menu-grid" id="menuGrid">
            <?php foreach ($menu_items as $item): ?>
                <?php
                $itemId = (int)$item['id'];
                $itemName = (string)$item['name'];
                $itemDesc = (string)($item['description'] ?? '');
                $itemPrice = (float)$item['price'];
                $itemSort = (int)($item['sort_order'] ?? 0);
                $isActive = !empty($item['is_active']);
                
                $imagePath = (string)($item['image_path'] ?? '');
                $imageWebPath = !empty($imagePath) ? '../' . $imagePath : '';
                $hasImage = !empty($imagePath) && file_exists(ROOT_PATH . DIRECTORY_SEPARATOR . $imagePath);
                
                // Prepare item JSON data for modal edit button safely
                $itemJson = htmlspecialchars(json_encode([
                    'id' => $itemId,
                    'name' => $itemName,
                    'description' => $itemDesc,
                    'price' => $itemPrice,
                    'sort_order' => $itemSort,
                    'is_active' => $isActive ? 1 : 0,
                    'image_path' => $imagePath,
                    'image_url' => $hasImage ? $imageWebPath : ''
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="menu-card <?= $isActive ? '' : 'is-hidden' ?>" 
                     id="item-card-<?= $itemId ?>"
                     data-id="<?= $itemId ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($itemName)) ?>"
                     data-desc="<?= htmlspecialchars(mb_strtolower($itemDesc)) ?>"
                     data-status="<?= $isActive ? 'active' : 'hidden' ?>">
                    
                    <!-- Image with Overlaid Badges -->
                    <div class="card-image-wrap">
                        <?php if ($hasImage): ?>
                            <img src="<?= htmlspecialchars($imageWebPath) ?>" alt="<?= htmlspecialchars($itemName) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                🍕
                                <span><?= htmlspecialchars(__('gallery_alt')) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Order Badge -->
                        <span class="badge-order">#<?= $itemSort ?></span>

                        <!-- Status Pill Badge (Clickable) -->
                        <span class="badge-status <?= $isActive ? 'active' : 'hidden' ?>" 
                              id="status-badge-<?= $itemId ?>"
                              onclick="toggleItemStatus(<?= $itemId ?>)"
                              title="<?= htmlspecialchars(__('display_in_menu')) ?>">
                            <?= $isActive ? '● ' . htmlspecialchars(__('status_active')) : '○ ' . htmlspecialchars(__('status_hidden')) ?>
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body">
                        <div class="card-title-row">
                            <h3 class="card-title"><?= htmlspecialchars($itemName) ?></h3>
                            <span class="card-price">¥<?= number_format($itemPrice) ?></span>
                        </div>
                        <p class="card-description"><?= nl2br(htmlspecialchars($itemDesc)) ?></p>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="card-footer">
                        <div class="card-toggle-wrap">
                            <label class="switch" title="<?= htmlspecialchars(__('display_in_menu')) ?>">
                                <input type="checkbox" 
                                       id="toggle-<?= $itemId ?>" 
                                       <?= $isActive ? 'checked' : '' ?>
                                       onchange="toggleItemStatus(<?= $itemId ?>)">
                                <span class="slider"></span>
                            </label>
                            <span><?= htmlspecialchars(__('display_in_menu')) ?></span>
                        </div>
                        <div class="card-buttons">
                            <button type="button" 
                                    class="btn btn-outline btn-sm" 
                                    onclick="openEditModal(<?= $itemJson ?>)">
                                ✏️ <?= htmlspecialchars(__('edit')) ?>
                            </button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('<?= htmlspecialchars(sprintf(__('confirm_delete_named'), $itemName), ENT_QUOTES) ?>')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= $itemId ?>">
                                <button type="submit" name="delete_item" class="btn btn-danger btn-sm" title="<?= htmlspecialchars(__('delete')) ?>">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Empty Search State -->
            <div class="empty-state" id="emptyState" style="display: <?= empty($menu_items) ? 'block' : 'none' ?>;">
                <div class="empty-state-icon">🍕</div>
                <h3><?= htmlspecialchars(__('no_data_available')) ?></h3>
                <p><?= htmlspecialchars(__('search_menu')) ?></p>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    ➕ <?= htmlspecialchars(__('add_new_item')) ?>
                </button>
            </div>
        </div>

    </div>

    <!-- ADD NEW ITEM MODAL -->
    <div class="modal-backdrop" id="addModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>➕ <?= htmlspecialchars(__('add_new_item')) ?></h3>
                <button type="button" class="modal-close" onclick="closeModals()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= htmlspecialchars(__('item_name')) ?> *</label>
                        <input type="text" name="name" required placeholder="e.g. Quattro Formaggi">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?= htmlspecialchars(__('item_price')) ?> *</label>
                            <input type="number" name="price" required min="0" step="10" placeholder="1500">
                        </div>
                        <div class="form-group">
                            <label><?= htmlspecialchars(__('sort_order')) ?></label>
                            <input type="number" name="sort_order" value="<?= $totalCount + 1 ?>" min="0" step="1">
                            <span class="form-help"><?= htmlspecialchars(__('sort_order_help')) ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= htmlspecialchars(__('item_description')) ?></label>
                        <textarea name="description" placeholder="Mozzarella, gorgonzola, parmesan, ricotta..."></textarea>
                    </div>
                    <div class="form-group">
                        <label><?= htmlspecialchars(__('upload_image')) ?></label>
                        <div class="upload-container" onclick="document.getElementById('addImageInput').click()">
                            <input type="file" id="addImageInput" name="image_file" accept="image/jpeg,image/png,image/gif" onchange="previewSelectedImage(this, 'addPreview')">
                            <div class="upload-prompt">
                                <span class="upload-prompt-icon">📷</span>
                                <strong><?= htmlspecialchars(__('drag_or_click_image')) ?></strong>
                                <span class="form-help">JPG, PNG, GIF (Max 5MB)</span>
                            </div>
                            <div class="image-preview-box" id="addPreview" style="display:none;">
                                <img src="" alt="Preview">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="switch-container" style="display:flex; align-items:center; gap:10px;">
                            <label class="switch">
                                <input type="checkbox" name="is_active" checked>
                                <span class="slider"></span>
                            </label>
                            <strong><?= htmlspecialchars(__('display_in_menu')) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModals()"><?= htmlspecialchars(__('cancel')) ?></button>
                    <button type="submit" name="add_item" class="btn btn-primary">➕ <?= htmlspecialchars(__('add_item')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT ITEM MODAL -->
    <div class="modal-backdrop" id="editModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3>✏️ <?= htmlspecialchars(__('edit_item')) ?></h3>
                <button type="button" class="modal-close" onclick="closeModals()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="image_path" id="editExistingImagePath">
                <div class="modal-body">
                    <div class="form-group">
                        <label><?= htmlspecialchars(__('item_name')) ?> *</label>
                        <input type="text" name="name" id="editName" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><?= htmlspecialchars(__('item_price')) ?> *</label>
                            <input type="number" name="price" id="editPrice" required min="0" step="10">
                        </div>
                        <div class="form-group">
                            <label><?= htmlspecialchars(__('sort_order')) ?></label>
                            <input type="number" name="sort_order" id="editSortOrder" min="0" step="1">
                            <span class="form-help"><?= htmlspecialchars(__('sort_order_help')) ?></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label><?= htmlspecialchars(__('item_description')) ?></label>
                        <textarea name="description" id="editDescription"></textarea>
                    </div>
                    <div class="form-group">
                        <label><?= htmlspecialchars(__('change_image')) ?></label>
                        <div class="upload-container" onclick="document.getElementById('editImageInput').click()">
                            <input type="file" id="editImageInput" name="image_file" accept="image/jpeg,image/png,image/gif" onchange="previewSelectedImage(this, 'editPreview')">
                            <div class="upload-prompt">
                                <span class="upload-prompt-icon">📷</span>
                                <strong><?= htmlspecialchars(__('drag_or_click_image')) ?></strong>
                                <span class="form-help">JPG, PNG, GIF (Max 5MB)</span>
                            </div>
                            <div class="image-preview-box" id="editPreview">
                                <img id="editPreviewImg" src="" alt="Current Image">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="switch-container" style="display:flex; align-items:center; gap:10px;">
                            <label class="switch">
                                <input type="checkbox" name="is_active" id="editIsActive">
                                <span class="slider"></span>
                            </label>
                            <strong><?= htmlspecialchars(__('display_in_menu')) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModals()"><?= htmlspecialchars(__('cancel')) ?></button>
                    <button type="submit" name="update_item" class="btn btn-success">💾 <?= htmlspecialchars(__('save')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
        const LABEL_ACTIVE = <?= json_encode(__('status_active')) ?>;
        const LABEL_HIDDEN = <?= json_encode(__('status_hidden')) ?>;
        let currentFilter = 'all';

        // Modal Controls
        function openAddModal() {
            document.getElementById('addModal').classList.add('is-open');
        }

        function openEditModal(item) {
            document.getElementById('editId').value = item.id;
            document.getElementById('editExistingImagePath').value = item.image_path || '';
            document.getElementById('editName').value = item.name;
            document.getElementById('editPrice').value = item.price;
            document.getElementById('editSortOrder').value = item.sort_order;
            document.getElementById('editDescription').value = item.description || '';
            document.getElementById('editIsActive').checked = item.is_active == 1;

            const previewBox = document.getElementById('editPreview');
            const previewImg = document.getElementById('editPreviewImg');
            if (item.image_url) {
                previewImg.src = item.image_url;
                previewBox.style.display = 'inline-block';
            } else {
                previewImg.src = '';
                previewBox.style.display = 'none';
            }

            document.getElementById('editImageInput').value = '';
            document.getElementById('editModal').classList.add('is-open');
        }

        function closeModals() {
            document.querySelectorAll('.modal-backdrop').forEach(modal => modal.classList.remove('is-open'));
        }

        // Close on backdrop click or ESC key
        document.querySelectorAll('.modal-backdrop').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModals();
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeModals();
        });

        // Instant Image Preview
        function previewSelectedImage(input, previewBoxId) {
            const previewBox = document.getElementById(previewBoxId);
            const previewImg = previewBox.querySelector('img');
            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    showToast('File size exceeds 5MB limit.', 'error');
                    input.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewBox.style.display = 'inline-block';
                };
                reader.readAsDataURL(file);
            }
        }

        // AJAX Quick Status Toggle
        async function toggleItemStatus(itemId) {
            const toggleInput = document.getElementById('toggle-' + itemId);
            const currentChecked = toggleInput ? toggleInput.checked : false;
            const newStatus = currentChecked ? 1 : 0;

            const formData = new FormData();
            formData.append('action', 'ajax_toggle');
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('id', itemId);
            formData.append('is_active', newStatus);

            try {
                const response = await fetch('menu.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const card = document.getElementById('item-card-' + itemId);
                    const badge = document.getElementById('status-badge-' + itemId);

                    if (newStatus === 1) {
                        card.classList.remove('is-hidden');
                        card.setAttribute('data-status', 'active');
                        badge.className = 'badge-status active';
                        badge.textContent = '● ' + LABEL_ACTIVE;
                        if (toggleInput) toggleInput.checked = true;
                    } else {
                        card.classList.add('is-hidden');
                        card.setAttribute('data-status', 'hidden');
                        badge.className = 'badge-status hidden';
                        badge.textContent = '○ ' + LABEL_HIDDEN;
                        if (toggleInput) toggleInput.checked = false;
                    }

                    updateBadgesCount();
                    applyFilters();
                    showToast(result.message || 'Updated');
                } else {
                    if (toggleInput) toggleInput.checked = !currentChecked;
                    showToast(result.error || 'Failed to update', 'error');
                }
            } catch (err) {
                if (toggleInput) toggleInput.checked = !currentChecked;
                showToast('Network error while toggling status', 'error');
            }
        }

        // Update Counter Badges
        function updateBadgesCount() {
            const cards = document.querySelectorAll('.menu-card');
            let active = 0;
            let hidden = 0;
            cards.forEach(card => {
                if (card.getAttribute('data-status') === 'active') active++;
                else hidden++;
            });
            const total = cards.length;

            const totalEl = document.getElementById('totalBadge');
            const activeEl = document.getElementById('activeBadge');
            const hiddenEl = document.getElementById('hiddenBadge');
            const tabAll = document.getElementById('tabCountAll');
            const tabActive = document.getElementById('tabCountActive');
            const tabHidden = document.getElementById('tabCountHidden');

            if (totalEl) totalEl.textContent = total;
            if (activeEl) activeEl.textContent = active + ' ' + LABEL_ACTIVE;
            if (hiddenEl) hiddenEl.textContent = hidden + ' ' + LABEL_HIDDEN;
            if (tabAll) tabAll.textContent = total;
            if (tabActive) tabActive.textContent = active;
            if (tabHidden) tabHidden.textContent = hidden;
        }

        // Filter and Search Logic
        function setFilter(filter, button) {
            currentFilter = filter;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            applyFilters();
        }

        function applyFilters() {
            const query = (document.getElementById('searchInput').value || '').trim().toLowerCase();
            const cards = document.querySelectorAll('.menu-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const name = card.getAttribute('data-name') || '';
                const desc = card.getAttribute('data-desc') || '';
                const status = card.getAttribute('data-status') || '';

                const matchesQuery = query === '' || name.includes(query) || desc.includes(query);
                const matchesFilter = currentFilter === 'all' || status === currentFilter;

                if (matchesQuery && matchesFilter) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            const emptyState = document.getElementById('emptyState');
            if (emptyState) {
                emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        }

        // Toast Message Notification
        function showToast(text, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast';
            if (type === 'error') toast.style.background = '#991b1b';
            toast.innerHTML = (type === 'error' ? '⚠️ ' : '✅ ') + text;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>