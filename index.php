<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=test', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
$groupId = (int)($_GET['group'] ?? 0);
$openGroups = isset($_GET['open']) ? explode(',', $_GET['open']) : [];

//Рекурсивная функция для отображения групп
function displayGroups($pdo, $parentId, $openGroups) {
    $stmt = $pdo->prepare("SELECT * FROM groups WHERE id_parent = :parent_id");
    $stmt->execute(['parent_id' => $parentId]);
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($groups) {
        echo '<ul>';
        foreach ($groups as $group) {
            $productCount = getProductCount($pdo, $group['id']);
            $isOpen = in_array($group['id'], $openGroups);
            $openGroupsList = $isOpen ? array_diff($openGroups, [$group['id']]) : array_merge($openGroups, [$group['id']]);
            $subgroupLink = '?group=' . $group['id'] . '&open=' . implode(',', $openGroupsList);
            echo '<li><a href="' . $subgroupLink . '">' . htmlspecialchars($group['name']) . " ($productCount)</a>";
            if ($isOpen) displayGroups($pdo, $group['id'], $openGroups);
            echo '</li>';
        }
        echo '</ul>';
    }
}
//Вывод групп товаров первого уровня
echo '<ul><li><a href="?group=0&open=' . implode(',', $openGroups) . '">Все товары</a></li>';
displayGroups($pdo, 0, $openGroups);
echo '</ul>';

//Вывод количества товаров
function getProductCount($pdo, $groupId) {
    $stmt = $pdo->prepare("
        WITH RECURSIVE group_hierarchy AS (
            SELECT id FROM groups WHERE id = :group_id
            UNION ALL
            SELECT g.id FROM groups g
            INNER JOIN group_hierarchy gh ON g.id_parent = gh.id
        )
        SELECT COUNT(*) FROM products WHERE id_group IN (SELECT id FROM group_hierarchy)
    ");
    $stmt->execute(['group_id' => $groupId]);
    return $stmt->fetchColumn();
}

//Вывод списка товаров
if ($groupId === 0) {
    $stmt = $pdo->query("SELECT * FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<h2>Все товары:</h2><ul>' . implode('', array_map(fn($product) => '<li>' . htmlspecialchars($product['name']) . '</li>', $products)) . '</ul>';
} elseif ($groupId > 0) {
    $stmt = $pdo->prepare("
        WITH RECURSIVE group_hierarchy AS (
            SELECT id FROM groups WHERE id = :group_id
            UNION ALL
            SELECT g.id FROM groups g
            INNER JOIN group_hierarchy gh ON g.id_parent = gh.id
        )
        SELECT * FROM products WHERE id_group IN (SELECT id FROM group_hierarchy)
    ");
    $stmt->execute(['group_id' => $groupId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo '<h2>Товары:</h2><ul>' . implode('', array_map(fn($product) => '<li>' . htmlspecialchars($product['name']) . '</li>', $products)) . '</ul>';
}
?>
