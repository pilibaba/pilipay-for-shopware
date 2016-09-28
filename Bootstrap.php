<?php

/**
 * (c) PILIBABA INTERNATIONAL CO.,LTD. <info@pilibaba.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Shopware\Models\Attribute\Dispatch as DispatchAttribute;
use Shopware\Models\Dispatch\Dispatch;
use Shopware\Models\Dispatch\ShippingCost;
use Shopware_Components_Pilipay_RestClient as RestClient;

class Shopware_Plugins_Frontend_PilibabaPilipaySystem_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{

    const PAYMENT_NAME = 'pilipay';
    const PILIBABA_WAREHOUSE_LIST_URL = 'http://www.pilibaba.com/pilipay/getAddressList';

    /**
     * Installation of the plugin
     *
     * @return array
     */
    public function install()
    {

        $compatibility = $this->getPluginInformationValue('compatibility', array());
        if (isset($compatibility['minimumVersion'])) {
            if ( ! $this->assertMinimumVersion($compatibility['minimumVersion'])) {
                return array(
                    'success' => false,
                    'message' => 'The Pilipay System plugin requires min. shopware ' . $compatibility['minimumVersion']
                );
            }
        }

        $this->createMyEvents();
        $this->createMyTable();
        $this->createMyPayment();
        $this->createMyMenu();
        $this->registerCustomModels();
        $this->createMyForm();
        $this->createDispatchAttributes();
        $this->createNewDispatch();
        $this->updateSchema();

        return array(
            'success'         => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template', 'theme')
        );
    }


    protected function updateSchema()
    {
        $this->registerCustomModels();

        $em   = $this->Application()->Models();
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);

        $classes = array(
            $em->getClassMetadata('Shopware\CustomModels\PilipayWarehouses\PilipayWarehouses')
        );

        try {
            $tool->dropSchema($classes);
        } catch (Exception $e) {
            //ignore
        }
        $tool->createSchema($classes);

        /**
         * Setting of the default warehouses
         */
        $this->addDemoData();
    }


    protected function addDemoData()
    {

        /**
         * Adding information about warehouses
         */
        $this->updateWarehouseList();

        $repository = Shopware()->Models()->getRepository('Shopware\Models\Customer\Customer');
        $builder    = $repository->createQueryBuilder('customer');
        $builder->addFilter(array('email' => 'pilibaba@pilibaba.com'));
        $user = $builder->getQuery()->getOneOrNullResult();

        if (empty($user)) {

            /**
             * Adding a pilibaba user
             */
            $repository = Shopware()->Models()->getRepository('Shopware\Models\Payment\Payment');
            $builder    = $repository->getAllPaymentsQueryBuilder(array('p.name' => 'pilipay'));
            $payment    = $builder->getQuery()->getOneOrNullResult();
            $paymentId  = $payment['id'];

            $sql = "INSERT INTO `s_user` ( `password`, `encoder`, `email`, `active`, `accountmode`, `confirmationkey`, `paymentID`, `firstlogin`, `lastlogin`, `sessionID`, `newsletter`, `validation`, `affiliate`, `customergroup`, `paymentpreset`, `language`, `subshopID`, `referer`, `pricegroupID`, `internalcomment`, `failedlogins`, `lockeduntil`) 
                    VALUES
                    ('$2y$105ze.VLOrHD/s7JJgj2MeJZVKfyZwiqDxCC', 'bcrypt', 'pilibaba@pilibaba.com', 1, 0, '', $paymentId, '2015-07-15', '2015-07-15 13:31:54', '', 0, '0', 0, 'EK', 8, '1', 1, '', NULL, '', 0, NULL)
                    ";

            Shopware()->Db()->query($sql);
            $userId = Shopware()->Db()->lastInsertId();

            $sql = "INSERT INTO `s_user_billingaddress` (`userID`, `company`, `department`, `salutation`, `firstname`, `lastname`, `street`, `zipcode`, `city`, `phone`, `countryID`, `stateID`, `ustid`, `additional_address_line1`, `additional_address_line2`) 
                    VALUES
                    ($userId, '', '', 'mr', 'Pilibaba', 'Pilibaba', 'Pilibaba', '10000', 'Pilibaba', '', 2, NULL, '', 'Pilibaba', 'Pilibaba')
                    ";
            Shopware()->Db()->query($sql);
            $billingaddressId = Shopware()->Db()->lastInsertId('s_user_billingaddress');

            $sql = "INSERT INTO `s_user_shippingaddress` (`userID`, `company`, `department`, `salutation`, `firstname`, `lastname`, `street`, `zipcode`, `city`, `countryID`, `stateID`, `additional_address_line1`, `additional_address_line2`) 
                    VALUES
                    ($userId, '', '', 'mr', 'Pilibaba', 'Pilibaba', 'Pilibaba', '10000', 'Pilibaba', 2, NULL, 'Pilibaba', 'Pilibaba')
                    ";
            Shopware()->Db()->query($sql);
            $shippingaddressId = Shopware()->Db()->lastInsertId('s_user_shippingaddress');

            $sql = "UPDATE `s_user` 
                    SET `default_billing_address_id` = $billingaddressId , 
                    `default_shipping_address_id` = $shippingaddressId
                    WHERE `id` = $userId";
            Shopware()->Db()->query($sql);
        }
    }

    protected function deleteDemoData()
    {
        $builder = Shopware()->Models()->createQueryBuilder();
        $builder->delete('Shopware\Models\Customer\Customer', 'customer')
                ->andWhere('customer.email = :email')
                ->setParameter('email', 'pilibaba@pilibaba.com')
                ->getQuery()
                ->execute();
    }

    // update the warehouse list
    public function updateWarehouseList()
    {
        $result = file_get_contents(self::PILIBABA_WAREHOUSE_LIST_URL);
        if (empty($result)) {
            return;
        }
        $array         = json_decode($result, true);
        $warehouseList = array();
        foreach ($array as $key => $value) {
            $warehouseList[] = array(
                'id'                => $value['id'],
                'name'              => $value['country'] . ' ' . $value['state'] . ' warehouse',
                'active'            => 0,
                'receiverFirstName' => $value['firstName'],
                'receiverLastName'  => $value['lastName'],
                'receiverPhone'     => $value['tel'],
                'street'            => $value['address'],
                'addressLine1'      => '',
                'addressLine2'      => '',
                'city'              => $value['city'],
                'zipCode'           => $value['zipcode'],
                'state'             => $value['state'],
                'country'           => $value['country'],
                'countryIsoCode'    => $value['iso2CountryCode'],
                'company'           => '',
            );
        }
        $sql = "truncate table `pilipay_warehouses`";
        Shopware()->Db()->query($sql);
        $fieldsList = array_keys($warehouseList[0]);
        $fields     = implode(', ', array_map(function ($s) { return '`' . $s . '`'; }, $fieldsList));
        $values     = implode(', ', array_map(function ($warehouse) use ($fieldsList) {
            return "(" . implode(", ", array_map(function ($field) use ($warehouse) {
                return "'" . strtr($warehouse[$field], array("\\" => "\\\\", "'" => "\\'")) . "'";
            }, $fieldsList)) . ")";
        }, $warehouseList));

        $sql = sprintf("INSERT INTO `pilipay_warehouses` ( %s ) VALUES %s", $fields, $values);
        Shopware()->Db()->query($sql);
    }

    //update plugin
    public function update()
    {
        $this->updateWarehouseList();
        $this->updatePaymentActive(true); // change to true

        return array(
            'success'         => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template', 'theme')
        );
    }


    /**
     * Uninstallation of the plugin
     *
     * @return array
     */
    public function uninstall()
    {

        $this->deactivateDispatch();
        $this->updatePaymentActive(false);
        $this->deleteDemoData();

        return array(
            'success'         => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'template', 'theme')
        );
    }

    /**
     * Activation of the pilipay plugin.
     * Setting of the active status to the payment methode
     *
     * @return array
     */
    public function enable()
    {

        $this->updatePaymentActive(true); // change to true

        return array(
            'success'         => true,
            'invalidateCache' => array('config', 'backend', 'proxy', 'frontend')
        );
    }

    /**
     * Update of the active payment method
     *
     * @param type $active
     */
    protected function updatePaymentActive($active = false)
    {
        $payment = $this->Payment();
        if ($payment !== null) {
            $payment->setActive($active);
            $this->get('models')->flush($payment);
        }
    }

    /**
     * Deactivation of the payment methode
     *
     * @return array
     */
    public function disable()
    {

        $this->updatePaymentActive(false);

        return array(
            'success'         => true,
            'invalidateCache' => array('config', 'backend')
        );
    }

    /**
     * Creation and saving of the config form
     */
    protected function createMyForm()
    {

        $form = $this->Form();

        $form->setElement('text', 'merchantNo', array(
            'label'       => 'Merchant No',
            'scope'       => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'description' => 'Register in www.pilibaba.com.you can get this number from your account info page.',
            'required'    => true,
            'value'       => ''
        ));

        $form->setElement('text', 'appSecrect', array(
            'label'       => 'Merchant account secrect',
            'scope'       => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'description' => 'Register in www.pilibaba.com.you can get this secrect number from your account info page.',
            'required'    => true,
            'value'       => ''
        ));

        $form->setElement('text', 'updateTrackNumber', array(
            'label'       => 'Update track number link',
            'scope'       => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'description' => 'Update track number',
            'required'    => true,
            'value'       => 'https://www.pilibaba.com/pilipay/updateTrackNo'
        ));

        $form->setElement('text', 'accessUrl', array(
            'label'       => 'Access URL',
            'scope'       => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'description' => 'Access URL',
            'required'    => true,
            'value'       => 'https://www.pilibaba.com/pilipay/payreq'
        ));

        $form->setElement('checkbox', 'allowGuestCheckout', array(
            'label'       => 'Allow Guest Checkout',
            'scope'       => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'description' => 'Enable it if you want to allow customers checkout when they have not logged in',
            'required'    => false,
            'value'       => ''
        ));

        $form->setElement('text', 'fixedTax', array(
            'label'       => 'Fixed Tax',
            'scope'       => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'description' => 'The fixed tax',
            'required'    => false,
            'value'       => ''
        ));

        $form->setElement('checkbox', 'useHttps', array(
            'label'       => 'Use HTTPS',
            'scope'       => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'description' => 'Enable it if you want to use HTTPS',
            'required'    => false,
            'value'       => true
        ));

        $form->setElement('text', 'shippingName', array(
            'label'       => 'Shipping Name',
            'scope'       => \Shopware\Models\Config\Element::SCOPE_SHOP,
            'description' => 'The name of the shipping to Pilibaba\'s warehouse. Leave it blank if you want to use the default shipping "Pilipay shipping" which is created autometically',
            'required'    => false,
            'value'       => ''
        ));
    }

    /**
     * Creation and saving of the payment methode
     */
    protected function createMyPayment()
    {

        if ($this->Payment()) {
            return;
        }

        $this->createPayment(array(
            'name'                  => self::PAYMENT_NAME,
            'description'           => 'Pilipay',
            'action'                => 'paymentpilipay/payment',
            'active'                => 1,
            'position'              => 0,
            'additionalDescription' => '<img src="//api.pilibaba.com/static/img/btn/for-shopware.png" alt="Pilibaba支付, 人民币支付, 直邮中国" style="height: 4rem;">',
        ));

        $pilipay = $this->Payment();
        if ($pilipay != null) {
            $pilipay->setPlugin(null);
            Shopware()->Models()->flush($pilipay);
        }
    }

    /**
     * Get all countries from database
     *
     * @return array list of countries
     */
    public function getCountryFirst()
    {
        return Shopware()
            ->Models()
            ->getRepository('Shopware\Models\Country\Country')
            ->getCountriesQuery()
            ->getArrayResult();
    }

    /**
     * Creation of new Pilipay shipment and shipping costs
     */
    private function createNewDispatch()
    {
        /* @var DispatchAttribute $dispatchAttribute */
        $dispatchAttribute = Shopware()->Models()->getRepository('Shopware\Models\Attribute\Dispatch')
                                       ->findOneBy(array('swagPilipayDispatch' => 1));
        $dispatchModel     = null;
        if ($dispatchAttribute) {
            $dispatchModel = $dispatchAttribute->getDispatch();
        }

        if ( ! $dispatchModel) {
            $dispatchModel = new Dispatch();
            $dispatchModel->setType(0);
            $dispatchModel->setName('Pilipay shipping');
            $dispatchModel->setDescription('');
            $dispatchModel->setComment('');
            $dispatchModel->setActive(1);
            $dispatchModel->setPosition(17);
            $dispatchModel->setCalculation(0);
            $dispatchModel->setStatusLink('');
            $dispatchModel->setSurchargeCalculation(0);
            $dispatchModel->setTaxCalculation(0);
            $dispatchModel->setBindLastStock(0);
            $dispatchModel->setBindShippingFree(0);

            // Convert the countries to there country models
            $countries = $this->getCountryFirst();
            foreach ($countries as $country) {
                if (empty($country['id'])) {
                    continue;
                }
                $countryModel = Shopware()->Models()->find('Shopware\Models\Country\Country', $country['id']);
                if ($countryModel instanceof Shopware\Models\Country\Country) {
                    $dispatchModel->getCountries()->add($countryModel);
                }
            }

            $dispatchAttribute = new DispatchAttribute();
            $dispatchAttribute->setSwagPilipayDispatch(1);

            $dispatchAttribute->fromArray(array('bepadoAllowed' => 0));
            $dispatchModel->setAttribute($dispatchAttribute);

            Shopware()->Models()->persist($dispatchModel);
            Shopware()->Models()->flush();
        }

        $shippingCosts = Shopware()->Models()->getRepository('Shopware\Models\Dispatch\ShippingCost')->findBy(
            array('dispatchId' => $dispatchModel->getId())
        );
        if ( ! $shippingCosts) {
            $shippingCost = new ShippingCost();
            $shippingCost->setFrom('0');
            $shippingCost->setValue(0);
            $shippingCost->setFactor(0);
            $shippingCost->setDispatch($dispatchModel);

            Shopware()->Models()->persist($shippingCost);
            Shopware()->Models()->flush();
        }
    }

    /**
     * Deactivation of the Pilipay shipment
     *
     * @return bool
     */
    protected function deactivateDispatch()
    {
        /* @var Dispatch $dispatchModel */
        $dispatchModels = Shopware()->Models()->getRepository('Shopware\Models\Dispatch\Dispatch')->findAll();

        foreach ($dispatchModels as $dispatchModel) {
            $dispatchAttributeModel = $dispatchModel->getAttribute();
            if ($dispatchAttributeModel) {
                if ($dispatchAttributeModel->getSwagPilipayDispatch()) {
                    $dispatchModel->setActive(0);
                    Shopware()->Models()->persist($dispatchModel);
                }
            }
        }
        Shopware()->Models()->flush();

        return true;
    }

    /**
     * Creation of shipment attributes
     */
    public function createDispatchAttributes()
    {
        Shopware()->Models()->addAttribute(
            's_premium_dispatch_attributes', 'swag', 'pilipay_dispatch', 'tinyint(1)'
        );
        Shopware()->Models()->generateAttributeModels(
            array(
                's_premium_dispatch_attributes',
            )
        );
    }

    /**
     * Search and return of the pilipay payment
     *
     * @return Shopware\Models\Payment\Payment
     */
    public function Payment()
    {
        return $this->Payments()->findOneBy(array('name' => self::PAYMENT_NAME));
    }

    public function afterInit()
    {
        $this->registerCustomModels();
    }

    /**
     * Creation and subscription to the events and hooks
     */
    protected function createMyEvents()
    {
        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Backend_PilipayWarehouses', 'onGetControllerPathBackend'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_PaymentPilipay', 'onGetControllerPathFrontend'
        );

        $this->subscribeEvent(
            'Enlight_Bootstrap_InitResource_PilipayClient', 'onInitResourcePilipayClient'
        );

        $this->subscribeEvent(
            'Shopware_Controllers_Backend_Order::saveAction::after', 'ShopwareControllersBackendOrderSaveActionAfter'
        );

        /**
         * Registration of templates
         */
        $this->subscribeEvent('Enlight_Controller_Action_PostDispatchSecure_Frontend', 'onPostDispatchFrontend', 110);


    }


    /**
     * Sending of Track Number to pilibaba after saving order in backend
     *
     * @param Enlight_Hook_HookArgs $arguments
     *
     * @return boolean
     */
    public function ShopwareControllersBackendOrderSaveActionAfter(Enlight_Hook_HookArgs $arguments)
    {
        $controller = $arguments->getSubject();
        $request    = $controller->Request()->getParams();

        $config = $this->get('plugins')->Frontend()->PilibabaPilipaySystem()->Config();

        if (isset($request)) {
            if (isset($request['paymentId']) && $this->Payment()->getId() == $request['paymentId'] &&
                isset($request['trackingCode']) && ! empty($request['trackingCode'])
            ) {
                $logisticsNo = $request['trackingCode'];

                $orderNo = (isset($request['number'])) ? $request['number'] : '';

                $params = array(
                    //Order number is created by the system payment API
                    'orderNo'     => $orderNo,
                    //You send parcels,express company gives your track number,you should give to me.
                    'logisticsNo' => $logisticsNo,
                    // Register in www.pilibaba.com.you can get this number from your account info page.
                    'merchantNo'  => $config->get('merchantNo')
                );

                $client = Shopware()->PilipayClient($config);

                $client->get($config->get('updateTrackNumber'), $params);
            }
        }

        return true;
    }


    public function onPostDispatchFrontend(Enlight_Event_EventArgs $args)
    {
        /* @var \Enlight_Controller_Action $subject */
        $subject = $args->getSubject();

        $view = $subject->View();
        $view->addTemplateDir(dirname(__FILE__) . '/Views/');
    }

    /**
     * Creation of the pilipay warehouses table
     */
    protected function createMyTable()
    {

        Shopware()->Db()->exec("CREATE TABLE IF NOT EXISTS `pilipay_warehouses` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `name` varchar(255) NOT NULL,
                                `active` int(1) NOT NULL,
                                `receiverFirstName` varchar(32) NOT NULL,
                                `receiverLastName` varchar(32) NOT NULL,
                                `receiverPhone` varchar(32) NOT NULL,
                                `addressLine1` varchar(512) NOT NULL,
                                `addressLine2` varchar(512) NOT NULL,
                                `zipCode` varchar(32) NOT NULL,
                                `city` varchar(128) NOT NULL,
                                `state` varchar(128) NOT NULL,
                                `country` varchar(128) NOT NULL,
                                `countryIsoCode` varchar(8) NOT NULL,
                                `company` varchar(256) NOT NULL,
                                PRIMARY KEY (`id`)
                              ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
        );
    }

    /**
     * Creation of the menu item
     */
    protected function createMyMenu()
    {

        $this->createMenuItem(array(
            'label'      => 'Pilibaba',
            'controller' => 'PilipayWarehouses',
            'class'      => 'sprite-application-block',
            'action'     => 'Index',
            'active'     => 1,
            'parent'     => $this->Menu()->findOneBy(['label' => 'Marketing'])
        ));
    }

    /**
     * Creation and return of the pilipay rest client for an event.
     *
     * @return \Shopware_Components_Pilipay_Client
     */
    public function onInitResourcePilipayClient()
    {
        $this->Application()
             ->Loader()
             ->registerNamespace('Shopware_Components_Pilipay', $this->Path() . 'Components/Pilipay/');
        $client = new Shopware_Components_Pilipay_Client($this->Config());

        return $client;
    }

    /**
     * Return of the path to a frontend controller for an event.
     *
     * @return string
     */
    public function onGetControllerPathFrontend()
    {
        $this->registerMyTemplateDir();

        return __DIR__ . '/Controllers/Frontend/PaymentPilipay.php';
    }

    /**
     * Return of the path to a backend controller for an event.
     *
     * @param Enlight_Event_EventArgs $args
     *
     * @return string
     */
    public function onGetControllerPathBackend(Enlight_Event_EventArgs $args)
    {

        $this->Application()->Template()->addTemplateDir(
            $this->Path() . 'Views/'
        );

        $this->registerCustomModels();

        return $this->Path() . '/Controllers/Backend/PilipayWarehouses.php';
    }

    /**
     * Registration of the template directory
     */
    public function registerMyTemplateDir()
    {
        $this->get('template')->addTemplateDir(
            __DIR__ . '/Views/'
        );
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function get($name)
    {
        if (version_compare(Shopware::VERSION, '4.2.0', '<') && Shopware::VERSION != '___VERSION___') {
            $name = ucfirst($name);

            return $this->Application()->Bootstrap()->getResource($name);
        }

        return parent::get($name);
    }

    /**
     * Return of the plugin label, which is displayed in the plugin information and
     * in the Plugin Manager
     *
     * @return string
     */
    public function getLabel()
    {
        return 'Pilipay';
    }

    /**
     * Geting plugin information file
     *
     * @return array
     */
    private function getPluginInformation()
    {
        $filename = __DIR__ . '/plugin.json';

        return (file_exists($filename)) ? json_decode(file_get_contents($filename), true) : false;
    }

    /**
     * Getting information value by key
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    private function getPluginInformationValue($key, $default = 'is not specified')
    {
        $info = $this->getPluginInformation();
        if ($info) {
            if (isset($info[$key])) {
                return $info[$key];
            } else {
                return $default;
            }
        }
    }

    /**
     * Return of the current version of the plugin
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->getPluginInformationValue('currentVersion');
    }

    /**
     * Return of the plugin information
     *
     * @return array
     */
    public function getInfo()
    {
        return array(
            'version'     => $this->getVersion(),
            'label'       => $this->getLabel(),
            'supplier'    => 'PILIBABA INTERNATIONAL CO.,LTD.',
            'description' => file_get_contents(__DIR__ . '/info.txt'),
            'link'        => 'http://www.pilibaba.com',
            'license'     => 'MIT',
            'author'      => 'PILIBABA INTERNATIONAL CO.,LTD. info@pilibaba.com',
            'copyright'   => '(c) PILIBABA INTERNATIONAL CO.,LTD. (http://www.pilibaba.com)',
            'changes'     => '[changelog]',
        );
    }

    /**
     * @return array
     */
    public function getCapabilities()
    {
        return array(
            'install' => true,
            'enable'  => true,
            'update'  => true
        );
    }

}
