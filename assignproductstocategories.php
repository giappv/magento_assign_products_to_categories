<?php
/**
* BeeHexa
* 
* @license http://opensource.org/licenses/osl-3.0.php
* @author Phan Vu Giap
* @email phangiap@beehexa.com
* @website https://shop.beehexa.com
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);
umask(0);

require_once dirname(__FILE__) . '/app/Mage.php';
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
//sku,cat1;cat2;cat3;cat4;cat5
define('PRODUCT_CATEGORIES_CSV', dirname(__FILE__) . '/var/import/productcategories/ibanh_categories.csv');
define('PRODUCT_CATEGORIES_CSV_DIR', dirname(__FILE__) . '/var/import/productcategories/');
define('ROOT_CATEGORY', 2);
define('UTF8', TRUE);

$do = filter_input(INPUT_GET, "do", FILTER_SANITIZE_SPECIAL_CHARS);

switch ($do) {
    case 'init':
        initQueue();
        break;
    case 'updatequeue':
        updateCSVFileToQueue();
        break;
    case 'resetqueue':
        resetQueue();
        break;
    case 'process':
    default :
        processQueue();
        break;
}

function assignProductToCategories($sku, $category_names) {
    $start_time = time();
    $parent_cat_id = ROOT_CATEGORY;
    //list of product category ids
    $product_categories = array();
    for ($i = 0; $i < count($category_names); $i++) {
        //update parent cat id
        if ($i > 0) {
            $parent_category_name = trim($category_names[$i - 1]);
            $parent_cat_id = getCategoryIdByName($parent_category_name, $parent_cat_id);
        }
        $cat_name = trim($category_names[$i]);
        $cat_id = getCategoryIdByName($cat_name, $parent_cat_id);
        if ($cat_id) {
            //$product_categories[] = getCategoryIdByName($cat_name, $parent_cat_id);
            $product_categories[] = $cat_id;
        }
    }

    $product = Mage::getModel('catalog/product')->getCollection()
            ->setStoreId("")
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('sku', array('eq' => $sku))
            ->getFirstItem();
    if ($product->getId()) {
        $oldCategoryIds = $product->getCategoryIds();
        $product->setCategoryIds(array_merge($oldCategoryIds, $product_categories));
        try {
            $product->save();
            echo $sku . ":" . "succesfully assigned to " . implode(";", $category_names) . "<br/>";
        } catch (Exception $ex) {
            echo $sku . ":" . $ex->getMessage() . "<br/>";
        }
    }
}

/**
 * 
 * @param type $category_name
 * @param type $parent_cat_id
 * @return boolean
 */
function getCategoryIdByName($category_name, $parent_cat_id) {
    //check category_name exist
    $parent_cat = Mage::getModel("catalog/category")->load($parent_cat_id);
    $cat = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('parent_id', array('eq' => $parent_cat_id))
            ->addAttributeToFilter('name', array('like' => $category_name))
            ->getFirstItem();
    //category exist
    if ($cat->getId()) {
        return $cat->getId();
    } else {
        $newcat = createNewCategory($category_name, $parent_cat);
        if ($newcat) {
            return $newcat->getId();
        } else {
            return false;
        }
    }
}

/**
 * create new category under parent category by name
 * @param type $category_name
 * @param type $parent_cat
 * @return boolean
 */
function createNewCategory($category_name, $parent_cat) {
    // create a new category item
    $category = Mage::getModel('catalog/category');
    $category->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID);

    $category->addData(array(
        'name' => trim($category_name),
        'meta_title' => trim($category_name),
        'display_mode' => Mage_Catalog_Model_Category::DM_PRODUCT,
        'is_active' => 1,
        'is_anchor' => 1,
        'path' => $parent_cat->getPath(),
    ));

    try {
        $category->save();
        return $category;
    } catch (Exception $e) {
        echo "ERROR: {$e->getMessage()}\n";
        return false;
    }
}

//create queue
function initQueue() {
    try {
        $resource = Mage::getSingleton('core/resource');
        $writeConnection = $resource->getConnection('core_write');
        $writeConnection->query("
            CREATE TABLE IF NOT EXISTS `hexa_import_product_categories` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `data` text NOT NULL,
              `createdAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `processed` tinyint(4) NOT NULL,
              `processedAt` datetime NOT NULL,
              `success` text NOT NULL,
              `error` text NOT NULL,
              `csvfile` varchar(100) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            ;
        ");
        $writeConnection->query("TRUNCATE TABLE hexa_import_product_categories");
        echo "Successfully init";
    } catch (Exception $ex) {
        echo $ex->getMessage();
    }
}

//update queue
function updateCSVFileToQueue() {

    foreach (glob(PRODUCT_CATEGORIES_CSV_DIR . "*.csv") as $filename) {
        if (!$file = fopen($filename, "r"))
        {
            echo ('Cannot open file:' . $filename);
            continue;
        }        
        $skus = "";
        while (($line = fgetcsv($file)) !== false) {
            if(UTF8){
                $skus .= $line[0] . "," . mb_convert_encoding( $line[1], "UTF-8", "auto");
                $line[1] = mb_convert_encoding( $line[1], "UTF-8", "auto");
            } else {
                $skus .= $line[0] . "," . $line[1];
            }
            
            
            //$line = json_encode($line);
            $line = json_encode( $line, JSON_UNESCAPED_UNICODE );
            $sql = "INSERT INTO hexa_import_product_categories (id,data,csvfile) values('','$line', '$filename')";
            $resource = Mage::getSingleton('core/resource');
            $writeConnection = $resource->getConnection('core_write');
            try {
                $writeConnection->query($sql);
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }
        }
        echo $skus . " are updated to queue";
        rename($filename, $filename . ".queued");
    }

    die();
}

//process queue
function processQueue() {
    $start = time();
    $resource = Mage::getSingleton('core/resource');
    $readConnection = $resource->getConnection('core_read');
    $last_id = filter_input(INPUT_GET, "id", FILTER_SANITIZE_SPECIAL_CHARS);
    
    if(empty($last_id))
    {
        $sql = "SELECT * FROM hexa_import_product_categories WHERE processed=0 order by id asc limit 1";
    } else {
        $sql = "SELECT * FROM hexa_import_product_categories WHERE processed=0 and id='$last_id'";
    }
    
    $results = $readConnection->fetchAll($sql);
    if (!count($results)) {
        exit("all items in queue has been processed");
    }

    $result = $results[0];
    $data = json_decode($result['data']);
    $sku = $data[0];
    $category_names = explode(";", $data[1]);
    try {
        assignProductToCategories($sku, $category_names);
        $last_id = $id = $result['id'];
        $readConnection->query("UPDATE hexa_import_product_categories SET processed=1,processedAt=NOW(),success='yes' WHERE id=$id");
        echo "succeeded within: " . (time() - $start) . " seconds";
    } catch (Exception $ex) {
        $error = $ex->getMessage();
        $readConnection->query("UPDATE hexa_import_product_categories SET processed=1,processedAt=NOW(),success='no',error='$error' WHERE id=$id");
        echo "failed within: " . (time() - $start) . " seconds";
    }
    $readConnection->closeConnection();
    $last_id++;
    echo "
    <script type=\"text/javascript\">
        setTimeout(\"location.href = '" . substr(Mage::getBaseUrl(), 0, -1) . "?id=$last_id';\", 100);
    </script>";
}

function resetQueue() {
    $resource = Mage::getSingleton('core/resource');
    $writeConnection = $resource->getConnection('core_write');
    $sql = "truncate table `hexa_import_product_categories`";
    try {
        $writeConnection->query($sql);
        echo "Queue is empty now!!!";
    } catch (Exception $ex) {
        echo $ex->getMessage();
    }
    $writeConnection->closeConnection();
}