<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ecommerce Management</title>
</head>
<body>
    <h2>Add New Category</h2>
    <form method="post">
        Category Name: <input type="text" name="category_name" required>
        <input type="submit" name="add_category" value="Add Category">
    </form>

    <h2>Add New Product</h2>
    <form method="post">
        Product Name: <input type="text" name="product_name" required>
        Price: <input type="number" step="0.01" name="price" required>
        Stock Quantity: <input type="number" name="stock_quantity" required>
        Category ID: <input type="number" name="category_id" required>
        <input type="submit" name="add_product" value="Add Product">
    </form>

    <h2>Place an Order</h2>
    <form method="post">
        Product ID: <input type="number" name="product_id" required>
        Order Quantity: <input type="number" name="order_quantity" required>
        <input type="submit" name="place_order" value="Place Order">
    </form>

    <?php
    $servername = "localhost";
    $username = "root";
    $password = ""; 
    $dbname = "ecommerce";

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Create order_logs table if not exists
    $createOrderLogsTable = "
        CREATE TABLE IF NOT EXISTS order_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT,
            order_quantity INT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $conn->query($createOrderLogsTable);

    // Create Trigger to log every new order in the order_logs table
    $conn->query("DROP TRIGGER IF EXISTS after_order_insert"); // Drop trigger if it already exists
    $createTrigger = "
        CREATE TRIGGER after_order_insert
        AFTER INSERT ON Orders
        FOR EACH ROW
        BEGIN
            INSERT INTO order_logs (order_id, order_quantity)
            VALUES (NEW.order_id, NEW.order_quantity);
        END
    ";
    $conn->query($createTrigger);

    // Add a new category
    if (isset($_POST['add_category'])) {
        $category_name = $_POST['category_name'];
        $sql = "INSERT INTO Categories (category_name) VALUES ('$category_name')";
        if ($conn->query($sql) === TRUE) {
            echo "New category added successfully.<br>";
        } else {
            echo "Error adding category: " . $conn->error . "<br>";
        }
    }

    // Add a new product
    if (isset($_POST['add_product'])) {
        $product_name = $_POST['product_name'];
        $price = $_POST['price'];
        $stock_quantity = $_POST['stock_quantity'];
        $category_id = $_POST['category_id'];

        $sql = "INSERT INTO Products (product_name, price, stock_quantity, category_id) VALUES ('$product_name', $price, $stock_quantity, $category_id)";
        if ($conn->query($sql) === TRUE) {
            echo "New product added successfully.<br>";
        } else {
            echo "Error adding product: " . $conn->error . "<br>";
        }
    }

    // Place an order and manage inventory
    if (isset($_POST['place_order'])) {
        $product_id = $_POST['product_id'];
        $order_quantity = $_POST['order_quantity'];

        // Check stock quantity
        $sql = "SELECT stock_quantity FROM Products WHERE product_id = $product_id";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $available_stock = $row['stock_quantity'];

            if ($order_quantity > $available_stock) {
                echo "Error: Not enough stock. Available stock is $available_stock.<br>";
            } else {
                $new_stock_quantity = $available_stock - $order_quantity;

                // Start transaction
                $conn->begin_transaction();
                try {
                    // Update stock in Products
                    $update_stock_sql = "UPDATE Products SET stock_quantity = $new_stock_quantity WHERE product_id = $product_id";
                    $conn->query($update_stock_sql);

                    // Insert order into Orders
                    $order_date = date("Y-m-d");
                    $insert_order_sql = "INSERT INTO Orders (product_id, order_quantity, order_date) VALUES ($product_id, $order_quantity, '$order_date')";
                    $conn->query($insert_order_sql);

                    // Commit transaction
                    $conn->commit();
                    echo "Order placed successfully. Remaining stock: $new_stock_quantity<br>";
                } catch (Exception $e) {
                    // Rollback on failure
                    $conn->rollback();
                    echo "Order failed: " . $e->getMessage() . "<br>";
                }
            }
        } else {
            echo "Product not found.<br>";
        }
    }

    // Display the updated Products table
    echo "<h2>Products Table</h2>";
    $sql = "SELECT * FROM Products";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Product ID</th><th>Product Name</th><th>Price</th><th>Stock Quantity</th><th>Category ID</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['product_id'] . "</td>";
            echo "<td>" . $row['product_name'] . "</td>";
            echo "<td>" . $row['price'] . "</td>";
            echo "<td>" . $row['stock_quantity'] . "</td>";
            echo "<td>" . $row['category_id'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No products found.";
    }

    // Step to delete the Categories table and check its impact on Products table
    // Delete the Categories table
    $conn->query("DROP TABLE IF EXISTS Categories");

    // Check impact on Products table (category_id should be NULL for all affected products)
    $checkImpact = $conn->query("SELECT * FROM Products WHERE category_id IS NULL");
    if ($checkImpact->num_rows > 0) {
        echo "The following products now have NULL as their category_id due to category deletion:<br>";
        while ($row = $checkImpact->fetch_assoc()) {
            echo "Product ID: " . $row['product_id'] . ", Product Name: " . $row['product_name'] . "<br>";
        }
    } else {
        echo "No products were affected by the deletion of categories.<br>";
    }

    $conn->close();
    ?>
</body>
<style>
    body{
        background-color:azure;
    }
</style>
</html>
