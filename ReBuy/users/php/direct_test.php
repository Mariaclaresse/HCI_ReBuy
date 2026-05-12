<!DOCTYPE html>
<html>
<head>
    <title>Direct Cart Test</title>
</head>
<body>
    <h2>Direct Cart Add Test</h2>
    
    <?php
    session_start();
    include 'db.php';
    
    echo "<h3>Session Status:</h3>";
    echo "<p>Session ID: " . session_id() . "</p>";
    echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
    echo "<p>Logged in: " . (isset($_SESSION['user_id']) ? 'Yes' : 'No') . "</p>";
    
    if (isset($_SESSION['user_id'])) {
        echo "<h3>Current Cart Items:</h3>";
        $stmt = $conn->prepare("SELECT c.*, p.name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<ul>";
            while ($row = $result->fetch_assoc()) {
                echo "<li>Product: " . htmlspecialchars($row['name']) . " (Quantity: " . $row['quantity'] . ")</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No items in cart</p>";
        }
        $stmt->close();
    }
    ?>
    
    <h3>Test Add to Cart:</h3>
    <form method="POST" action="cart_add.php">
        <input type="hidden" name="product_id" value="2">
        <input type="number" name="quantity" value="1" min="1">
        <button type="submit">Add Product 2 to Cart</button>
    </form>
    
    <h3>Test AJAX:</h3>
    <button onclick="testAJAX()">Test AJAX Add to Cart</button>
    <div id="result"></div>
    
    <script>
    function testAJAX() {
        const formData = new FormData();
        formData.append('product_id', '2');
        formData.append('quantity', '1');
        
        fetch('cart_add.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(data => {
            console.log('Response data:', data);
            document.getElementById('result').innerHTML = '<pre>' + data + '</pre>';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = 'Error: ' + error;
        });
    }
    </script>
    
    <p><a href="product_details.php?id=2">Back to Product Details</a></p>
</body>
</html>
