<?php
session_start();
include 'db.php';

// Search functionality
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';
$page = $_GET['page'] ?? 1;
$sort = $_GET['sort'] ?? 'default';
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build ORDER BY clause based on sort
$order_by = 'ORDER BY id DESC'; // default
switch ($sort) {
    case 'price_low':
        $order_by = 'ORDER BY price ASC';
        break;
    case 'price_high':
        $order_by = 'ORDER BY price DESC';
        break;
}

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
$sql = "SELECT id, name, description, price, original_price, image_url, rating, category FROM products $where_clause $order_by LIMIT ? OFFSET ?";
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
    $products[] = $row;
}
$stmt->close();

// Get categories for filter (only categories with in-stock items)
$categories_result = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND stock_quantity > 0 ORDER BY category");
$categories = [];
$other_category = null;
while ($row = $categories_result->fetch_assoc()) {
    $cat = $row['category'];
    if (strtolower($cat) === 'other') {
        $other_category = $cat;
    } else {
        $categories[] = $cat;
    }
}
// Add 'Other' at the end if it exists
if ($other_category) {
    $categories[] = $other_category;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReBuy</title>
    <link rel="icon" type="image/x-icon" href="../../assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <style>
        .shop-hero {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%);
            color: white;
            padding: 30px 0 30px;
            text-align: center;
        }
        .shop-hero h1 {
            font-size: 42px;
            margin-bottom: 15px;
            font-weight: 700;
        }
        .shop-hero p {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 30px;
        }
        .search-container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        .search-form {
            display: flex;
            background: white;
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: none;
            font-size: 14px;
            outline: none;
            color: #333;
        }
        .search-input::placeholder {
            color: #999;
        }
        .search-btn {
            background: #2d5016;
            color: white;
            border: none;
            padding: 16px 20px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .search-btn:hover {
            background: #4a7c2e;
        }
        .shop-layout {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 40px;
        }
        .category-filter {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .category-filter h3 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }
        .category-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .category-list li {
            margin-bottom: 10px;
        }
        .category-list a {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            color: #666;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .category-list a:hover {
            background: #f8f9fa;
            color: #2d5016;
        }
        .category-list a.active {
            background: #2d5016;
            color: white;
        }
        .category-list i {
            margin-right: 10px;
            width: 20px;
        }
        .main-content {
            min-height: 1000px;
        }
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .results-info h2 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 24px;
        }
        .results-info p {
            margin: 0;
            color: #666;
        }
        .sort-dropdown {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            font-size: 14px;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 60px;
        }
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 420px;
        }
        .product-card {
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .product-image {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .product-image:hover img {
            transform: scale(1.05);
        }
        .discount-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4444;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .product-info {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .product-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin: 0 0 10px 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 44px;
            max-height: 44px;
        }
        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .current-price {
            font-size: 18px;
            font-weight: 600;
            color: #2d5016;
        }
        .original-price {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
        }
        .btn-add-cart {
            width: 100%;
            background: #2d5016;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
            margin-top: auto;
        }
        .btn-add-cart:hover {
            background: #4a7c2e;
        }
        .recommendations {
            background: #f8f9fa;
            padding: 60px 0;
            margin-top: 60px;
        }
        .recommendations-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .recommendations h2 {
            text-align: center;
            font-size: 32px;
            margin-bottom: 40px;
            color: #333;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }
        .pagination a, .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
        }
        .pagination a:hover {
            background: #f8f9fa;
            color: #2d5016;
        }
        .pagination span.active {
            background: #2d5016;
            color: white;
            border-color: #2d5016;
        }
        @media (max-width: 768px) {
            .shop-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .category-filter {
                position: static;
            }
        }
    </style>
</head>
<body>

     <?php include '_header.php'; ?>

    <!-- Shop Hero Section -->
    <section class="shop-hero">
        <div class="container">
            <h1>Shop</h1>
            <p>Discover our amazing collections</p>
            <div class="search-container">
                <form class="search-form" method="GET" action="shop.php">
                    <input type="text" name="search" class="search-input" placeholder="Search for products..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Shop Layout -->
    <div class="shop-layout">
        <!-- Category Filter Sidebar -->
        <aside class="category-filter">
            <h3>Categories</h3>
            <ul class="category-list">
                <li>
                    <a href="?category=all&search=<?php echo urlencode($search); ?>" class="<?php echo $category === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-th"></i> All Products
                    </a>
                </li>
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="?category=<?php echo urlencode($cat); ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $category === $cat ? 'active' : ''; ?>">
                            <i class="fas fa-couch"></i> <?php echo htmlspecialchars(ucfirst($cat)); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="results-info">
                    <h2><?php echo !empty($search) ? 'Search Results' : 'All Products'; ?></h2>
                    <p>Showing <?php echo count($products); ?> of <?php echo $total_products; ?> results</p>
                </div>
                <div class="sort-options">
                    <select class="sort-dropdown" onchange="window.location.href=this.value">
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'default'])); ?>" <?php echo $sort === 'default' ? 'selected' : ''; ?>>Default Sorting</option>
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_low'])); ?>" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_high'])); ?>" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    </select>
                </div>
            </div>

            <div class="products-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" onclick="window.location.href='product_details.php?id=<?php echo $product['id']; ?>'">
                            <div class="product-image">
                                <img src="<?php echo !empty($product['image_url']) ? '../' . htmlspecialchars($product['image_url']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                                    <span class="discount-badge">-<?php echo round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>%</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <div class="product-price">
                                    <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                                    <?php if ($product['original_price']): ?>
                                        <span class="original-price">₱<?php echo number_format($product['original_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-shopping-bag"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px;">
                        <i class="fas fa-search" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                        <h3 style="color: #999; margin-bottom: 10px;">No products found</h3>
                        <p style="color: #ccc;">Try adjusting your search or browse our categories</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-shopping-bag"></i>
                        <span>ReBuy</span>
                    </div>
                    <p class="footer-text">ReBuy lets you buy quality second-hand items for less, saving money while supporting a more sustainable lifestyle.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="about_us.php">About Us</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Customer Services</h3>
                    <ul>
                        <li><a href="settings.php">My Account</a></li>
                        <li><a href="#">Track Your Order</a></li>
                        <li><a href="#">Returns</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Our Information</h3>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Condition</a></li>
                        <li><a href="#">Return Policy</a></li>
                        <li><a href="#">Shipping Info</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p class="footer-text"><i class="fas fa-phone"></i> +639813446215</p>
                    <p class="footer-text"><i class="fa-solid fa-envelope"></i> rebuy@gmail.com</p>
                    <p class="footer-text"><i class="fa-solid fa-location-dot"></i> T. Curato St. Cabadbaran City Agusan Del Norte, Philippines, 8600</p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; Copyright @ 2026 <strong>ReBuy</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    <style>
        .search-loading {
            display: none;
            position: absolute;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            color: #2d5016;
        }
        .search-loading.show {
            display: block;
        }
        .search-loading i {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .search-results-info {
            transition: opacity 0.3s ease;
        }
        .products-grid {
            transition: opacity 0.3s ease;
        }
        .products-grid.loading {
            opacity: 0.6;
        }
    </style>
    <script>
        let searchTimeout;
        let currentSearchTerm = '';
        let currentPage = 1;

        // Add to cart functionality
        function addToCart(productId) {
            // Here you would typically make an AJAX call to add to cart
            // For now, we'll show a success message
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Added!';
            button.style.background = '#28a745';
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = '';
            }, 2000);
            
            // You can implement actual cart functionality here
            console.log('Added product ID:', productId);
        }

        // User dropdown menu
        document.querySelector('.icon-btn').addEventListener('click', function() {
            document.querySelector('.user-dropdown').classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            if (!userMenu.contains(event.target)) {
                document.querySelector('.user-dropdown').classList.remove('active');
            }
        });

        // Real-time search functionality
        function performRealTimeSearch(searchTerm, page = 1) {
            const searchInput = document.querySelector('.search-input');
            const loadingIndicator = document.querySelector('.search-loading');
            const productsGrid = document.querySelector('.products-grid');
            const pagination = document.querySelector('.pagination');
            const resultsInfo = document.querySelector('.results-info');
            
            // Show loading indicator
            loadingIndicator.classList.add('show');
            productsGrid.classList.add('loading');
            
            // Get current category
            const currentCategory = new URLSearchParams(window.location.search).get('category') || 'all';
            
            // Create AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `search_ajax.php?search=${encodeURIComponent(searchTerm)}&category=${currentCategory}&page=${page}`, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    loadingIndicator.classList.remove('show');
                    productsGrid.classList.remove('loading');
                    
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Update products grid
                                productsGrid.innerHTML = response.html;
                                
                                // Update pagination
                                if (pagination) {
                                    pagination.outerHTML = response.pagination;
                                } else if (response.pagination) {
                                    productsGrid.insertAdjacentHTML('afterend', response.pagination);
                                }
                                
                                // Update results info
                                if (resultsInfo) {
                                    const searchTitle = searchTerm ? 'Search Results' : 'All Products';
                                    resultsInfo.innerHTML = `
                                        <h2>${searchTitle}</h2>
                                        <p>Showing ${response.showing} of ${response.total_products} results</p>
                                    `;
                                }
                                
                                // Update current page for pagination
                                currentPage = response.current_page;
                                currentSearchTerm = searchTerm;
                                
                                // Re-attach pagination event listeners
                                attachPaginationListeners();
                            }
                        } catch (e) {
                            console.error('Error parsing search response:', e);
                        }
                    }
                }
            };
            xhr.send();
        }

        // Debounced search function
        function debouncedSearch(searchTerm) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (searchTerm !== currentSearchTerm) {
                    currentPage = 1;
                    performRealTimeSearch(searchTerm, currentPage);
                }
            }, 500); // 500ms delay
        }

        // Attach pagination event listeners
        function attachPaginationListeners() {
            document.querySelectorAll('.pagination a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.getAttribute('data-page');
                    if (page) {
                        performRealTimeSearch(currentSearchTerm, parseInt(page));
                        // Scroll to top of products
                        document.querySelector('.products-grid').scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        }

        // Initialize real-time search when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            const searchForm = document.querySelector('.search-form');
            
            // Add loading indicator to search container
            const searchContainer = document.querySelector('.search-container');
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'search-loading';
            loadingIndicator.innerHTML = '<i class="fas fa-spinner"></i>';
            searchContainer.appendChild(loadingIndicator);
            
            // Get initial search term from URL
            const urlParams = new URLSearchParams(window.location.search);
            const initialSearch = urlParams.get('search') || '';
            currentSearchTerm = initialSearch;
            
            // Add real-time search event listener
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                debouncedSearch(searchTerm);
            });
            
            // Prevent form submission and handle it via AJAX
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const searchTerm = searchInput.value.trim();
                currentPage = 1;
                performRealTimeSearch(searchTerm, currentPage);
            });
            
            // Attach initial pagination listeners
            attachPaginationListeners();
        });
    </script>
