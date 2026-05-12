<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Get search parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query based on search and category
$where_conditions = [];
$params = [];
$types = '';

// Always exclude out-of-stock items
$where_conditions[] = "stock_quantity > 0";

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if ($category !== 'all') {
    $where_conditions[] = "category = ?";
    $params[] = $category;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total products
$count_sql = "SELECT COUNT(*) as total FROM products $where_clause";
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $per_page);
$stmt->close();

// Get products
$sql = "SELECT id, name, description, price, original_price, image_url, rating, category FROM products $where_clause LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$bind_params = $params;
$bind_types = $types . 'ii';
$bind_params[] = $per_page;
$bind_params[] = $offset;
$stmt->bind_param($bind_types, ...$bind_params);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = [
        'id' => $row['id'],
        'name' => htmlspecialchars($row['name']),
        'description' => htmlspecialchars($row['description']),
        'price' => $row['price'],
        'original_price' => $row['original_price'],
        'image_url' => $row['image_url'],
        'rating' => $row['rating'],
        'category' => $row['category']
    ];
}
$stmt->close();

// Generate HTML for products
$html = '';
if (count($products) > 0) {
    foreach ($products as $product) {
        $discount_percentage = '';
        if ($product['original_price'] && $product['original_price'] > $product['price']) {
            $discount_percentage = '<span class="discount-badge">-' . round((($product['original_price'] - $product['price']) / $product['original_price']) * 100) . '%</span>';
        }
        
        $original_price_html = '';
        if ($product['original_price']) {
            $original_price_html = '<span class="original-price">₱' . number_format($product['original_price'], 2) . '</span>';
        }
        
        $image_url = !empty($product['image_url']) ? '../' . htmlspecialchars($product['image_url']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80';
        
        $html .= '
            <div class="product-card" onclick="window.location.href=\'product_details.php?id=' . $product['id'] . '\'">
                <div class="product-image">
                    <img src="' . $image_url . '" alt="' . $product['name'] . '">
                    ' . $discount_percentage . '
                </div>
                <div class="product-info">
                    <h3 class="product-name">' . $product['name'] . '</h3>
                    <div class="product-price">
                        <span class="current-price">₱' . number_format($product['price'], 2) . '</span>
                        ' . $original_price_html . '
                    </div>
                    <button class="btn-add-cart" onclick="addToCart(' . $product['id'] . ')">
                        <i class="fas fa-shopping-bag"></i> Add to Cart
                    </button>
                </div>
            </div>';
    }
} else {
    $html = '
        <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
            <i class="fas fa-search" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
            <h3 style="color: #999; margin-bottom: 10px;">No products found</h3>
            <p style="color: #ccc;">Try adjusting your search or browse our categories</p>
        </div>';
}

// Generate pagination HTML
$pagination_html = '';
if ($total_pages > 1) {
    $pagination_html = '<div class="pagination">';
    
    // Previous buttons
    if ($page > 1) {
        $pagination_html .= '<a href="#" data-page="1"><i class="fas fa-angle-double-left"></i></a>';
        $pagination_html .= '<a href="#" data-page="' . ($page - 1) . '"><i class="fas fa-angle-left"></i></a>';
    }
    
    // Page numbers
    for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
        if ($i == $page) {
            $pagination_html .= '<span class="active">' . $i . '</span>';
        } else {
            $pagination_html .= '<a href="#" data-page="' . $i . '">' . $i . '</a>';
        }
    }
    
    // Next buttons
    if ($page < $total_pages) {
        $pagination_html .= '<a href="#" data-page="' . ($page + 1) . '"><i class="fas fa-angle-right"></i></a>';
        $pagination_html .= '<a href="#" data-page="' . $total_pages . '"><i class="fas fa-angle-double-right"></i></a>';
    }
    
    $pagination_html .= '</div>';
}

// Return JSON response
echo json_encode([
    'success' => true,
    'html' => $html,
    'pagination' => $pagination_html,
    'total_products' => $total_products,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'showing' => count($products)
]);
?>
