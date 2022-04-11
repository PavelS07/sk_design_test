<?php
class Products {
    // реализацию подключения к бд, нужно делать в отельном классе с данными для подключения  в конфиге
    private $dbName = 'sk_design';
    private $dbUSer = 'pavel';
    private $dbPassword = 's2189sjd';

    private function connectDb() {
        // можно обработать исключение try..catch
        return new PDO("mysql:host=localhost;dbname={$this->dbName}", "{$this->dbUSer}", "{$this->dbPassword}");
    }
    public function getMainGroups() {
        $db = $this->connectDb();
        $stmt = $db->prepare("SELECT id, id_parent, name FROM `groups` WHERE id_parent=0");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getAllProducts() {
        $db = $this->connectDb();
        $stmt = $db->prepare("SELECT name FROM `products` ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllCategories() {
        $db = $this->connectDb();
        $stmt = $db->prepare("SELECT id, id_parent FROM `groups`");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getProductsById($groupId) {
        $subGroups = implode(' , ', $this->getAllSubCategories($groupId));

        $db = $this->connectDb();
        $stmt = $db->prepare("SELECT name FROM `products` WHERE id_group IN ({$subGroups}) ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // рекурсивно ищем главную группу
    public function searchMainGroupId($groupId) {
        $array = $this->getAllCategories();
        foreach ($array as $k => $val) {
            if ($groupId == $val['id']) {
                if($array[$k]['id_parent'] == 0) return $val['id'];
                else return $this->searchMainGroupId($array[$k]['id_parent']);
            }
        }
    }
    public function getAllSubCategories($groupId) {
        $groups = $this->getAllCategories();

        // Найденнные подкаталоги
        $subGroups = [];
        array_push($subGroups, $groupId);

        // Ищем родителя и каталоги до $groupId
        foreach ($groups as $val) {
            if(in_array($val['id_parent'], $subGroups)) {
                array_push($subGroups, $val['id']);
            }
        }

        return $subGroups;
    }

    public function getSubCategoriesById($groupId) {
        $groups = $this->getAllCategories();
        $mainGroup = $this->searchMainGroupId($groupId);

        // Найденнные подкаталоги
        $subGroups = [];
        array_push($subGroups, $mainGroup);

        // Ищем каталоги, у которых родитель $groupId
        foreach ($groups as $val) {

            if(in_array($val['id_parent'], $subGroups)) {
                array_push($subGroups, $val['id']);
            }
        }

        // удаляем корневую директорию
        unset($subGroups[0]);

        return $subGroups;
    }

    public function getCountProducts($groupId) {
        $subGroups = implode(' , ', $this->getAllSubCategories($groupId));

        $db = $this->connectDb();
        $stmt = $db->prepare("SELECT COUNT(*) as 'count' FROM `products` WHERE id_group IN ({$subGroups})"); // тут можно подготовить запрос через ? или :query_param
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_NUM)[0];
    }

    public function getSubCategoriesInfo($groupId) {
        if(!$groupId) return false;
        $subGroups = implode(' , ', $this->getSubCategoriesById($groupId));
        // для формирования конечного массива
        $data = [];

        $db = $this->connectDb();
        $stmt = $db->prepare("SELECT id, id_parent, name FROM `groups` WHERE id IN ({$subGroups})");
        $stmt->execute();

        $query = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($query as  $arr)  $data[$arr['id_parent']][] = $arr;

        return $data;
    }
}

$products = new Products();

echo '<a href="/">Все товары</a>';

$id = isset($_GET['group']) ? (int)$_GET['group'] : false;
$data = $products->getSubCategoriesInfo($id);
$mainId = $products->searchMainGroupId($id);
// От корня до выбранной категории
$subCategories[$mainId] = $data[$mainId];
$subCategories[$id] = @$data[$id];

echo '<div style="display: flex;"><ul style="margin-right: 15px;">';
// Каталоги, которые не имеют потомков скрываются после выбора
// Для формирования вложенности html можно использовать рекурсию
foreach ($products->getMainGroups() as $groups) {
    if($groups['id'] == $id) $color='green';
    else $color = '#000000';
    echo "<li><a style='color:{$color}' href='/?group={$groups['id']}'>".$groups['name']."</a> {$products->getCountProducts($groups['id'])}</li>";

    if(!empty($subCategories) && $subCategories != false && isset($subCategories[$groups['id']])) {

        foreach ($subCategories as $key => $val) {

            if($key > $id || ($key == $id && $key != $mainId)) break;

                echo '<ul>';
                foreach ($val as $value) {
                    if($value['id'] == $id) $color='green';
                    else $color = '#000000';

                    echo "<li><a style='color:{$color}' href='/?group={$value['id']}'>".$value['name']."</a> {$products->getCountProducts($value['id'])}</li>";
                    if(isset($subCategories[$id]) && $id != $mainId && $value['id'] == $id) {
                        echo '<ul>';
                        foreach ($subCategories[$id] as $value) {
                            echo "<li><a href='/?group={$value['id']}'>".$value['name']."</a> {$products->getCountProducts($value['id'])}</li>";
                        }
                        echo '</ul>';
                        unset($subCategories[$id]);
                    }
                }
            echo '</ul>';
        }
    }

}

echo '</ul><div>';

// Дефолтный вывод товаров
if(!$id)  foreach ($products->getAllProducts() as $product)  echo '<p>'.$product['name'].'</p>';
else foreach ($products->getProductsById($id) as $product)  echo '<p>'.$product['name'].'</p>';

echo '</div></div>';
