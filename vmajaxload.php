<?php
defined('_JEXEC') or die;

// Define constants if not already defined
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', dirname(dirname(dirname(__FILE__))));
}

// Load Joomla framework
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

// Import required Joomla libraries
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

// Load VirtueMart configuration
if (!class_exists('VmConfig')) {
    require_once(JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php');
    VmConfig::loadConfig();
}

// Load VirtueMart plugin base class
if (!class_exists('vmPlugin')) {
    require_once(JPATH_ADMINISTRATOR . '/components/com_virtuemart/plugins/vmplugin.php');
}

class PlgSystemVmajaxload extends JPlugin
{
    protected $autoloadLanguage = true;
    protected $app;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->app = JFactory::getApplication();
        
        if ($this->app->isClient('site')) {
            // $this->app->enqueueMessage('VmAjaxLoad Plugin Constructor Called', 'notice');
        }
    }

    public function onBeforeCompileHead()
    {
        if ($this->app->isClient('site') && 
            $this->app->input->get('option') === 'com_virtuemart' && 
            $this->app->input->get('view') === 'category') {
            
            $document = JFactory::getDocument();
            
            // Get category ID from current URL path
            $uri = JUri::getInstance();
            $path = $uri->getPath();
            
            // Extract category alias from path
            $segments = explode('/', trim($path, '/'));
            $categoryAlias = end($segments);
            
            // Get category ID from alias
            $db = JFactory::getDbo();
            $query = $db->getQuery(true)
                ->select('virtuemart_category_id')
                ->from('#__virtuemart_categories_' . VmConfig::$vmlang)
                ->where('slug = ' . $db->quote($categoryAlias));
            
            $db->setQuery($query);
            $category_id = $db->loadResult();
            
            if (!$category_id) {
                // Try to get from standard VirtueMart params
                $category_id = $this->app->input->getInt('virtuemart_category_id', 0);
            }
            
            // Add debug output
            $debug = array(
                'path' => $path,
                'alias' => $categoryAlias,
                'category_id' => $category_id
            );
            
            // Add JavaScript file with category ID and debug info
            $js = "
                document.addEventListener('DOMContentLoaded', function() {
                    console.log('Debug info:', " . json_encode($debug) . ");
                    var container = document.querySelector('.product-wrap.grid.ajaxprod');
                    if (container) {
                        container.dataset.categoryId = '" . $category_id . "';
                    }
                });
            ";
            $document->addScriptDeclaration($js);
            
            // Add JavaScript file
            $document->addScript(JURI::root(true) . '/plugins/system/vmajaxload/js/vmajaxload.js');
            
            // Add CSS file
            $document->addStyleSheet(JURI::root(true) . '/plugins/system/vmajaxload/css/vmajaxload.css');
        }
    }

    public function onAjaxVmajaxload()
    {
        if (!$this->app->isClient('site')) {
            return;
        }

        $input = $this->app->input;
        $page = $input->getInt('page', 1);
        $category_id = $input->getInt('category_id', 0);
        $productsPerPage = 12;

        try {
            if (!class_exists('VmConfig')) {
                require_once(JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php');
                VmConfig::loadConfig();
            }

            if (!class_exists('VirtueMartModelProduct')) {
                require_once(JPATH_ADMINISTRATOR . '/components/com_virtuemart/models/product.php');
            }

            $productModel = VmModel::getModel('product');
            
            // Set up the model
            $productModel->filter_order = 'p.virtuemart_product_id';
            $productModel->filter_order_Dir = 'DESC';
            
            // Set category filter
            if ($category_id > 0) {
                $productModel->virtuemart_category_id = $category_id;
                
                // Force category filter
                if (method_exists($productModel, 'setCategoryFilter')) {
                    $productModel->setCategoryFilter();
                }
            }

            // Get total number of products in category
            $totalProducts = $productModel->getTotal();
            
            // Calculate offset and limit
            $limitStart = ($page - 1) * $productsPerPage;
            
            // Get products for current page with category filter
            $products = $productModel->getProductListing(
                $productModel->filter_order,
                $productsPerPage,
                $limitStart,
                true
            );
            
            if (!empty($products)) {
                $productModel->addImages($products);
            }

            // Debug information
            $debug = array(
                'category_id' => $category_id,
                'total_products' => $totalProducts,
                'current_page' => $page,
                'limit_start' => $limitStart,
                'products_count' => count($products)
            );

            // Get currency
            if (!class_exists('CurrencyDisplay')) {
                require_once(JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/currencydisplay.php');
            }
            $currency = CurrencyDisplay::getInstance();

            ob_start();
            if (!empty($products)) {
                foreach ($products as $product) {
                    if(!is_object($product) or empty($product->link)) {
                        continue;
                    }
                    if (!empty($product)) {
                        $product_cellwidth = 'col-lg-3 col-md-4 col-sm-6 col-xs-12';
                        ?>
                        <div class="product-block catprod <?php echo $product_cellwidth; ?> b1c-good" itemtype="http://schema.org/Product" itemprop="itemListElement" itemscope="">
                            <div class="spacer product-container card">
                                <?php echo shopFunctionsF::renderVmSubLayout('vmlabel',array('product'=>$product)); ?>

                                <?php echo shopFunctionsF::renderVmSubLayout('customfields',array('product'=>$product,'position'=>'ikonki')); ?>

                                <div class="product-image">
                                    <?php echo shopFunctionsF::renderVmSubLayout('productday',array('product'=>$product)); ?>
                                    <div class="vm-trumb-slider">
                                        <div class="flyblok">
                                            <a title="<?php echo htmlspecialchars($product->product_name) ?>" href="<?php echo $product->link; ?>">
                                                <?php 
                                                if (!empty($product->images[0])) {
                                                    echo $product->images[0]->displayMediaThumb('class="img-rounded flyimg'.$product->virtuemart_product_id.'"', false);
                                                }
                                                ?>
                                            </a>
                                        </div>
                                        <?php
                                        $number = 4;
                                        if ($number > count($product->images)) {
                                            $number = count($product->images);
                                        }
                                        for ($i = 1; $i < $number; $i++) { ?>
                                            <div>
                                                <a class="kit" title="<?php echo htmlspecialchars($product->product_name) ?>" href="<?php echo $product->link; ?>">
                                                    <img class="img-rounded" data-lazy="<?php echo JURI::root(true) . '/' . $product->images[$i]->file_url; ?>">
                                                </a>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="product-info">
                                    <div class="product-name b1c-name" itemprop="name">
                                        <?php echo JHtml::link($product->link, $product->product_name, ' class="kit" itemprop="url"'); ?>
                                    </div>

                                    <div class="clearfix"></div>

                                    <div class="product-stock-wrap">
                                        <?php if (VmConfig::get('display_stock', 1)): ?>
                                            <div class="product-stock">
                                                <?php echo shopFunctionsF::renderVmSubLayout('stockhandle',array('product'=>$product)); ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="product-review">
                                            <span>
                                                <?php
                                                $options = array();
                                                $options['object_id'] = $product->virtuemart_product_id;
                                                $options['object_group'] = 'com_virtuemart';
                                                $options['published'] = 1;
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="product-details" itemtype="http://schema.org/Offer" itemprop="offers" itemscope>
                                    <?php 
                                    echo shopFunctionsF::renderVmSubLayout('prices',array('product'=>$product,'currency'=>$currency));
                                    echo "<meta itemprop='price' content='".$product->prices['salesPrice']."'>";
                                    echo "<meta itemprop='priceCurrency' content='".$currency->_vendorCurrency_code_3."'>";
                                    ?>

                                    <div class="ves">
                                        <?php echo shopFunctionsF::renderVmSubLayout('customfields',array('product'=>$product,'position'=>'ves')); ?>
                                    </div>

                                    <?php if (!empty($product->product_sku)): ?>
                                        <div class="product-article">
                                            <?php echo JText::_('COM_VIRTUEMART_PRODUCT_SKU') . ': ' . $product->product_sku; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($product->product_s_desc)): ?>
                                    <div class="proddescription" itemprop="description">
                                        <p>
                                            <?php
                                            $wordCount = 9;
                                            $outputText = implode(' ', (array_slice(explode(' ', $product->product_s_desc), 0, $wordCount))) . ' ...';
                                            echo $outputText;
                                            ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div class="product-cart">
                                    <?php echo shopFunctionsF::renderVmSubLayout('addtocart',array('product'=>$product, 'position' => array('ontop', 'addtocart'))); ?>
                                </div>

                                <div class="dops">
                                    <?php echo JHtml::_('vmessentials.addtowishlist', $product, $iconOnly); ?>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
            }
            $html = ob_get_clean();

            // Calculate if there are more products
            $currentlyLoaded = $limitStart + count($products);
            $hasMore = $currentlyLoaded < $totalProducts;

            // Get pagination info
            $pageInfo = array(
                'total' => $totalProducts,
                'loaded' => $currentlyLoaded,
                'current_page' => $page,
                'total_pages' => ceil($totalProducts / $productsPerPage),
                'per_page' => $productsPerPage,
                'offset' => $limitStart,
                'category_id' => $category_id
            );

            echo json_encode(array(
                'success' => true,
                'html' => $html,
                'hasMore' => $hasMore,
                'pagination' => $pageInfo,
                'debug' => $debug
            ));

        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => array(
                    'page' => $page,
                    'category_id' => $category_id,
                    'message' => $e->getMessage()
                )
            ));
        }

        $this->app->close();
    }
}