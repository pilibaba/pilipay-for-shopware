<?php

/**
 * (c) PILIBABA INTERNATIONAL CO.,LTD. <info@pilibaba.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Shopware_Controllers_Frontend_PaymentPilipay extends Shopware_Controllers_Frontend_Payment
{

    public $sSYSTEM;
    /**
     * Reference to sAdmin object (core/class/sAdmin.php)
     *
     * @var sAdmin
     */
    protected $admin;
    protected $basePathUrl = '';
    protected $basePath = '';

    /**
     * @var \Shopware_Plugins_Frontend_PilibabaPilipaySystem_Bootstrap
     */
    private $plugin;

    /**
     * @var \Enlight_Components_Session_Namespace
     */
    private $session;

    /**
     * @var \Enlight_Config
     */
    private $pluginConfig;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->admin = Shopware()->Modules()->Admin();
        $this->plugin = $this->get('plugins')->Frontend()->PilibabaPilipaySystem();
        $this->pluginConfig = $this->plugin->Config();
        $this->session = $this->get('session');
        $this->sSYSTEM = Shopware()->System();
        $this->basePath = Shopware()->Shop()->getHost() . Shopware()->Shop()->getBasePath() . Shopware()->Shop()->getBaseUrl();
        $this->basePathUrl = $this->Request()->getScheme() . '://' . $this->basePath;
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (version_compare(Shopware::VERSION, '4.2.0', '<') && Shopware::VERSION != '___VERSION___') {
            if ($name == 'pluginlogger') {
                $name = 'log';
            }
            $name = ucfirst($name);
            return Shopware()->Bootstrap()->getResource($name);
        }
        return Shopware()->Container()->get($name);
    }

    /**
     * Pre dispatch method
     */
    public function preDispatch()
    {
        $this->View()->setScope(Enlight_Template_Manager::SCOPE_PARENT);
    }

    /**
     * Return of the information, whether current user is logged in
     *
     * @return bool
     */
    public function isUserLoggedIn()
    {
        return (isset($this->session->sUserId) && !empty($this->session->sUserId));
    }

    /**
     * Payment Pilibaba controller
     * EntryURL: /paymentpilipay/payment
     */
    public function paymentAction()
    {
        /**@var $context Shopware\Components\Routing\Context */
        if ($this->shouldUseHttps() && !Shopware()->Front()->Request()->isSecure()){
            return $this->redirect(str_replace('http://', 'https://', Shopware()->Front()->Router()->assemble(array(
                'controller' => 'paymentpilipay',
                'action' => 'payment',
                'sUseSSL' => true,
            ))));
        }

        /**
         * Loading of the user
         */
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Customer\Customer');
        $builder = $repository->createQueryBuilder('customer');

        // configuration of this plugin
        $config = $this->pluginConfig;

        /**
         * Check, whether the user has an account
         */
        if ($userId = $this->session->offsetGet('sUserId')) {
            $builder->addFilter(array('id' => $userId));
        } else if (!$config->get('allowGuestCheckout')) {
            return $this->forward('login', 'account', null, array(
                'sTarget' => 'paymentpilipay',
                'sTargetAction' => 'payment',
                'sUseSSL' => true,
                'showNoAccount' => true,
            ));
        } else {
            /**
             * pilibaba user ID 1
             */
            $builder->addFilter(array('email' => 'pilibaba@pilibaba.com'));
        }

        $user = $builder->getQuery()->getOneOrNullResult();

        /**
         * Getting payment method for Pilibaba
         */
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Payment\Payment');

        $builder = $repository->getAllPaymentsQueryBuilder(array('p.name' => 'pilipay'));

        $payment = $builder->getQuery()->getOneOrNullResult();

        $basket = Shopware()->Modules()->Basket()->sGetBasket();

        /**
         * If the basket is empty, the user is forwarded to the home page
         */
        if (empty($basket)) {
            return $this->forward('cart', 'checkout');
        }

        // disable cache
        $this->Response()->setHeader('Cache-Control', 'private');

        $order = Shopware()->Modules()->Order();
        $order->sBasketData = $basket;
        $order->sUserData["additional"]["user"]["id"] = $user->getId();


        $repository = Shopware()->Models()->getRepository('Shopware\Models\Customer\Shipping');
        $builder = $repository->createQueryBuilder('shipping');
        $builder->addFilter(array('customerId' => $user->getId()));
        $shipping = $builder->getQuery()->getOneOrNullResult();

        $repository = Shopware()->Models()->getRepository('Shopware\Models\Customer\Billing');
        $builder = $repository->createQueryBuilder('billing');
        $builder->addFilter(array('customerId' => $user->getId()));
        $billing = $builder->getQuery()->getOneOrNullResult();

        /**@var $warehouse \Shopware\CustomModels\PilipayWarehouses\PilipayWarehouses */
        $warehouse = Shopware()->Models()->getRepository('Shopware\CustomModels\PilipayWarehouses\PilipayWarehouses')->findOneBy(array('active'=>1));
        if (!$warehouse){
            return $this->reportError('No warehouse is activated! Please contact the store manager to activate a warehouse firstly.');
        }

        $country = Shopware()->Models()->getRepository('Shopware\Models\Country\Country')->findOneBy(array('iso' => $warehouse->getCountryIsoCode()));
        if (!$country) {
            return $this->reportError("The warehouse's country is not valid: " . $warehouse->getCountry() . '/' . $warehouse->getCountryIsoCode());
        }

        // todo: the state...
        $state_s = $this->formatsrt($warehouse->getState());
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Country\State');
        $builder = $repository->createQueryBuilder('states');
        $builder->where('states.name LIKE ?1')
                ->setParameter(1, $state_s);

        $state = $builder->getQuery()->getOneOrNullResult();

        /**
         * TODO  create states
         */

        if ($billing) {
            $order->sUserData["billingaddress"]["userID"] = $user->getId();
            $order->sUserData["billingaddress"]["company"] = $billing->getCompany();
            $order->sUserData["billingaddress"]["zipcode"] = $billing->getZipCode();
            $order->sUserData["billingaddress"]["city"] = $billing->getCity();
            $order->sUserData["billingaddress"]["street"] = $billing->getStreet();
            $order->sUserData["billingaddress"]["fax"] = $billing->getFax();
            $order->sUserData["billingaddress"]["countryID"] = $billing->getCountryId();
            $order->sUserData["billingaddress"]["ustid"] = '';
            $order->sUserData["billingaddress"]["customernumber"] = $billing->getNumber();
            $order->sUserData["billingaddress"]["department"] = $billing->getDepartment();
            $order->sUserData["billingaddress"]["salutation"] = $billing->getSalutation();
            $order->sUserData["billingaddress"]["firstname"] = $billing->getFirstName();
            $order->sUserData["billingaddress"]["lastname"] = $billing->getLastName();
            $order->sUserData["billingaddress"]["phone"] = $billing->getPhone();

//            $order->sUserData["billingaddress"]["salutation"] = $user->getId();

//            if ($shipping) {
//                $order->sUserData["shippingaddress"]["userID"] = $user->getId();
//                $order->sUserData["shippingaddress"]["company"] = $shipping->getCompany();
//                $order->sUserData["shippingaddress"]["zipcode"] = $shipping->getZipCode();
//                $order->sUserData["shippingaddress"]["city"] = $shipping->getCity();
//                $order->sUserData["shippingaddress"]["street"] = $shipping->getStreet();
//                $order->sUserData["shippingaddress"]["fax"] = $billing->getFax();
//                $order->sUserData["shippingaddress"]["countryID"] = $billing->getCountryId();
//                $order->sUserData["shippingaddress"]["ustid"] = '';
//
//                $order->sUserData["shippingaddress"]["customernumber"] = $billing->getNumber();
//                $order->sUserData["shippingaddress"]["department"] = $shipping->getDepartment();
//                $order->sUserData["shippingaddress"]["salutation"] = $shipping->getSalutation();
//                $order->sUserData["shippingaddress"]["firstname"] = $shipping->getFirstName();
//                $order->sUserData["shippingaddress"]["lastname"] = $shipping->getLastName();
//                $order->sUserData["shippingaddress"]["phone"] = $shipping->getId();
////                $order->sUserData["shippingaddress"]["additional_address_line1"] = $shipping->getAdditionalAddressLine1();
////                $order->sUserData["shippingaddress"]["additional_address_line2"] = $shipping->getAdditionalAddressLine2();
//
//            } else {
//                $order->sUserData["shippingaddress"]["userID"] = $user->getId();
//                $order->sUserData["shippingaddress"]["company"] = $billing->getCompany();
//                $order->sUserData["shippingaddress"]["zipcode"] = $billing->getZipCode();
//                $order->sUserData["shippingaddress"]["city"] = $billing->getCity();
//                $order->sUserData["shippingaddress"]["street"] = $billing->getStreet();
//                $order->sUserData["shippingaddress"]["fax"] = $billing->getFax();
//                $order->sUserData["shippingaddress"]["countryID"] = $billing->getCountryId();
//                $order->sUserData["shippingaddress"]["ustid"] = '';
//
//                $order->sUserData["shippingaddress"]["customernumber"] = $billing->getNumber();
//                $order->sUserData["shippingaddress"]["department"] = $billing->getDepartment();
//                $order->sUserData["shippingaddress"]["salutation"] = $billing->getSalutation();
//                $order->sUserData["shippingaddress"]["firstname"] = $billing->getFirstName();
//                $order->sUserData["shippingaddress"]["lastname"] = $billing->getLastName();
//                $order->sUserData["shippingaddress"]["phone"] = $billing->getPhone();
//            }


            $order->sUserData["shippingaddress"]["userID"] = $user->getId();
            $order->sUserData["shippingaddress"]["company"] = $billing->getCompany();
            $order->sUserData["shippingaddress"]["zipcode"] = $warehouse->getZipCode();
            $order->sUserData["shippingaddress"]["city"] = $warehouse->getCity();
            $order->sUserData["shippingaddress"]["street"] = $warehouse->getStreet();
            $order->sUserData["shippingaddress"]["fax"] = $billing->getFax();
            $order->sUserData["shippingaddress"]["countryID"] = $country ? $country->getId() : '';

            $order->sUserData["shippingaddress"]["stateID"] = $state ? $state->getId() : '';
            $order->sUserData["shippingaddress"]["ustid"] = '';

            $order->sUserData["shippingaddress"]["customernumber"] = $billing->getNumber();
            $order->sUserData["shippingaddress"]["department"] = $billing->getDepartment();
            $order->sUserData["shippingaddress"]["salutation"] = $billing->getSalutation();
            $order->sUserData["shippingaddress"]["firstname"] = $warehouse->getReceiverFirstName();
            $order->sUserData["shippingaddress"]["lastname"] = $warehouse->getReceiverLastName();
            $order->sUserData["shippingaddress"]["phone"] = $billing->getPhone();
            $order->sUserData["shippingaddress"]["additional_address_line1"] =  $warehouse->getAddressLine1();
            $order->sUserData["shippingaddress"]["additional_address_line2"] = $warehouse->getAddressLine2();
        }


        $order->sUserData["additional"]["user"]["paymentID"] = $payment['id'];
        $order->sBasketData["AmountWithTaxNumeric"] = !empty($basket['AmountWithTaxNumeric']) ? $basket['AmountWithTaxNumeric'] : $basket['AmountNumeric'];;
        $order->sBasketData["invoice_amount"] = $basket["AmountWithTaxNumeric"];
        $order->sBasketData["invoice_amount_net"] = $basket["AmountNetNumeric"];
        $order->sBasketData["AmountNetNumeric"] = $basket['AmountNetNumeric'];
        $order->sBasketData["invoice_amount"] = $basket["AmountWithTaxNumeric"];
        $order->sAmount = $basket['Amount'];
        $order->sComment = Shopware()->Session()->sComment;
        $order->bookingId = 'pilibaba' . '_' . uniqid();
        $order->uniqueID = 'pilibaba' . '_' . uniqid();

        /**@var \Shopware\Models\Attribute\Dispatch $dispatchAttribute */
        /**@var \Shopware\Models\Dispatch\Dispatch $dispatchModel */

        $shippingName = trim($config->get('shippingName'));

        if (empty($shippingName)){
            $dispatchAttribute = Shopware()->Models()->getRepository('Shopware\Models\Attribute\Dispatch')
                                                            ->findOneBy(array('swagPilipayDispatch' => 1));
            if ($dispatchAttribute) {
                $dispatchModel = $dispatchAttribute->getDispatch();
            }
        } else {
            $dispatchModel = Shopware()->Models()->getRepository('Shopware\Models\Dispatch\Dispatch')
                                                            ->findOneBy(array('name' => $shippingName));
        }

        if (!$dispatchModel){
            $this->reportError("Cannot find a proper shipping method!");
            return;
        }

        $this->session->offsetSet('sDispatch', $dispatchModel->getId());
        $country = array('id' => $dispatchModel->getCountries()->first()->getId());
        $shippingcosts = $this->admin->sGetPremiumShippingcosts($country);
        if (!isset($shippingcosts['value'])){
            $shippingcosts['value'] = $shippingcosts['netto'];
        }

        $order->sShippingcosts = $shippingcosts['value'];
        $order->sShippingcostsNumeric = $shippingcosts['value'];
        $order->setOrderStatus(Shopware()->Models()->find('Shopware\Models\Order\Status', 0)); // 0: not processing

        /**
         * Saving of order data
         */
        $orderNum = $order->sSaveOrder();


        Shopware()->PluginLogger()->info("Save order " . $orderNum);

        $allowCur = array('USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY');
        if (!in_array($this->sSYSTEM->sCurrency["currency"], $allowCur)) {
            $logger = $this->get('corelogger');
            $logger->error($this->sSYSTEM->sCurrency["currency"] . ' currency is not support Pilibaba');
        }

        $client = Shopware()->PilipayClient($config);

        $repository = Shopware()->Models()->getRepository('Shopware\Models\Order\Order');
        $builder = $repository->createQueryBuilder('orders');
        $builder->addFilter(array('number' => $orderNum));
        $order = $builder->getQuery()->getOneOrNullResult();

        $time = date("Y-m-d H:i:s");

        $param = array();

        $amoutbasket = str_replace(',', '.', $basket['Amount']) * 100;

        $param['merchantNO'] = $config->get('merchantNo');
        $param['currencyType'] = $this->sSYSTEM->sCurrency["currency"];
        $param['orderNo'] = $orderNum;
        $param['orderAmount'] = (string)$amoutbasket;
        //yyyy-MM-dd HH:mm:ss
        $param['orderTime'] = $order->getOrderTime()->format('Y-m-d H:i:s');
        $param['sendTime'] = $time;

        //checkout/cart
        $param['pageUrl'] = $this->basePathUrl . '/checkout/cart';

        $param['serverUrl'] = $this->basePathUrl . '/paymentpilipay/complite';
        $param['shipper'] = $shippingcosts['value'] * 100;

        $param['signType'] = 'MD5';
        $param['signMsg'] = md5($config->get('merchantNo') . $orderNum . $amoutbasket . $time . $config->get('appSecrect'));

        $product = '';
        $tax = '';
        $surcharge = 0;

        foreach ($basket['content'] as $key => $value) {
            // check product is surcharge
            if ($value['ordernumber'] == 'sw-surcharge') {
                $surcharge = ($value['price'] * $value['quantity']) * 100;
                continue;
            }

            $tmp = json_encode((object)array('name' => $value['articlename'],
                'pictureURL' => (isset($value['image']['src']['original'])) ? $value['image']['src']['original'] : '',
                'productURL' => $this->basePathUrl . '/' . $value['linkDetails'],
//                'price' => (string) $value['additional_details']['price'] * 100,
                'price' => (string)str_replace(',', '.', $value['price']) * 100,
                'productId' => (string)(isset($value['additional_details']['articleID']) ? $value['additional_details']['articleID'] : $value['articleID']),
                'quantity' => (string)$value['quantity'],
                'length' => (string)(isset($value['additional_details']['length']) ? $value['additional_details']['length'] : 0),
                'height' => (string)(isset($value['additional_details']['height']) ? $value['additional_details']['height'] : 0),
                'width' => (string)(isset($value['additional_details']['width']) ? $value['additional_details']['width'] : 0),
                'weight' => (string)intval(str_replace(',', '.', (isset($value['additional_details']['weight']) ? $value['additional_details']['weight'] : 0)) * 1000)));

            if ($product) {
                $product .= ',' . $tmp;
            } else {
                $product = $tmp;
            }

            $tax += $value['tax'];
        }

        // if the surcharge is set
        if ($surcharge) {
            $param['shipper'] = (string)($param['shipper'] + $surcharge);
        }

        /*
         * Total tax sum for products in basket
         */
//        $param['tax'] = $tax * 100;

        /**
         * Tax 0 is set for all customers
         */
        $param['tax'] = $config->get('fixedTax') * 100;

        $param['goodsList'] = urlencode('[' . $product . ']');

        echo '<form action="' . $client->getBaseUri() . '" method="post" name="pilibaba" >';

        foreach ($param as $name => $value) {
            echo "<input type='hidden' name='" . $name . "' value='" . $value . "'>";

            Shopware()->PluginLogger()->info("Pilibaba payment " . $name . ' ' . $value);
        }


        echo '</form> <script language="JavaScript">' . 'document.pilibaba.submit();' . '</script>';

        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
    }

    private function formatsrt($str)
    {
        $str = trim($str);
        $first = mb_substr($str, 0, 1, 'UTF-8');//первая буква
        $last = mb_substr($str, 1);//все кроме первой буквы
        $first = mb_strtoupper($first, 'UTF-8');
        $last = mb_strtolower($last, 'UTF-8');
        $name1 = $first . $last;
        return $name1;
    }

    /**
     * Saving of status order after successful payment
     */
    public function compliteAction()
    {

        $config = $this->get('plugins')->Frontend()->PilibabaPilipaySystem()->Config();
        $merchantNO = $this->Request()->getParam('merchantNO');
        $orderNo = $this->Request()->getParam('orderNo');
        $orderAmount = $this->Request()->getParam('orderAmount');
        $signType = $this->Request()->getParam('signType');
        $payResult = $this->Request()->getParam('payResult');
        $signMsg = $this->Request()->getParam('signMsg');
        $dealId = $this->Request()->getParam('dealId');
        $fee = $this->Request()->getParam('fee');
        $sendTime = $this->Request()->getParam('sendTime');


        if ($config->get('merchantNo') == $merchantNO
            && md5($merchantNO . $orderNo . $orderAmount . $sendTime . $config->get('appSecrect')) == $signMsg
        ) {
            $repository = Shopware()->Models()->getRepository('Shopware\Models\Order\Order');
            $builder = $repository->createQueryBuilder('orders');
            $builder->addFilter(array('number' => $orderNo));
            $order = $builder->getQuery()->getOneOrNullResult();

            if ($payResult == 10) {

                $this->setPaymentStatus($order->getTransactionId(), 12);
                $url = $this->basePathUrl . '/paymentpilipay/finish?orderNo='.$orderNo;

                echo '<result>1</result><redirecturl>' . $url . '</redirecturl>';
                $this->Front()->Plugins()->ViewRenderer()->setNoRender();

                if ($order->getOrderStatus() && $order->getOrderStatus()->getId() == 0) {
                    $this->setOrderStatus($order->getTransactionId(), 0); // in process
                }
            } elseif ($payResult == 11) {
                $this->setPaymentStatus($order->getTransactionId(), 21);

            }
        }

    }

    /**
     * Order status is set and if necessary, a status email is sent to the user
     *
     * @param string $transactionId
     * @param int $paymentStatusId
     */
    public function setPaymentStatus($transactionId, $paymentStatusId)
    {
        $sql = 'SELECT id
                FROM s_order
                WHERE transactionID=?
                    AND status!=-1';
        $orderId = Shopware()->Db()->fetchOne($sql, array(
            $transactionId
        ));
//
        /**
         * TODO creates config for sending email
         */

//        $config = Shopware()->Plugins()->Frontend()->PilibabaPilipaySystem()->Config();
//        $sendStatusMail = (bool)$config->paymentStatusMail;

        $order = Shopware()->Modules()->Order();

//        $order->setPaymentStatus($orderId, $paymentStatusId, $sendStatusMail);
        $order->setPaymentStatus($orderId, $paymentStatusId);
    }

    /**
     * If the payment is successful
     *
     * @return Enlight_View_Default
     */
    public function finishAction()
    {
        // if the user has not logged in, login fristly
        if (!$this->session['sUserId']){
            return $this->forward('login', 'account', null, array(
                'sTarget' => 'paymentpilipay',
                'sTargetAction' => 'finish',
                'sUseSSL' => true,
            ));
        }

        Shopware()->Modules()->Basket()->sRefreshBasket();

        $orderNo = intval($this->Request()->getParam('orderNo'));
        if (!$orderNo){
            return $this->redirect(array('action' => 'orders', 'controller' =>  'account'));
        }

        if (!is_object($this->session['sOrderVariables'])){
            return $this->redirect(array('action' => 'orders', 'controller' =>  'account'));
        }

        $view = $this->View();
        $view->assign($this->session['sOrderVariables']->getArrayCopy());
        if ($orderNo != $view->sOrderNumber){
            return $this->redirect(array('action' => 'orders', 'controller' =>  'account'));
        }

        // fetch order data
        $repository = Shopware()->Models()->getRepository('Shopware\Models\Order\Order');
        $builder = $repository->createQueryBuilder('orders');
        $builder->addFilter(array('number' => $orderNo));
        /**@var $order Shopware\Models\Order\Order */
        $order = $builder->getQuery()->getOneOrNullResult();
        if (!$order){
            return $this->redirect(array('action' => 'orders', 'controller' =>  'account'));
        }

        $view->sOrderNumber = $order->getNumber();
        $view->sTransactionnumber = $order->getTransactionId();
        $view->sPayment = array(
            'description' => $order->getPayment()->getDescription() ?: $order->getPayment()->getName()
        );

//        $view->sDispatch = null;

        // fetch user data
        $userData = $view->sUserData;
        $userData['shippingaddress'] = array(
            'company' => '',
            'salutation' => 'mr',
            'firstname' => $userData['shippingaddress']['firstname'],
            'lastname' => $userData['shippingaddress']['lastname'],
            'street' => '(最终的收货地址是您在霹雳爸爸上所填写的地址)',
        );
        $userData['additional'] = null;
        $view->sUserData = $userData;


        return $view->loadTemplate('frontend/checkout/finish.tpl');
    }

    /**
     * Get complete user-data as an array to use in view
     *
     * @return array
     */
    public function getUserData()
    {
        $system = Shopware()->System();
        $userData = $this->admin->sGetUserData();
        if (!empty($userData['additional']['countryShipping'])) {
            $sTaxFree = false;
            if (!empty($userData['additional']['countryShipping']['taxfree'])) {
                $sTaxFree = true;
            } elseif (
                !empty($userData['additional']['countryShipping']['taxfree_ustid'])
                && !empty($userData['billingaddress']['ustid'])
                && $userData['additional']['country']['id'] == $userData['additional']['countryShipping']['id']
            ) {
                $sTaxFree = true;
            }

            $system->sUSERGROUPDATA = Shopware()->Db()->fetchRow("
                SELECT * FROM s_core_customergroups
                WHERE groupkey = ?
            ", array($system->sUSERGROUP));

            if (!empty($sTaxFree)) {
                $system->sUSERGROUPDATA['tax'] = 0;
                $system->sCONFIG['sARTICLESOUTPUTNETTO'] = 1; //Old template
                Shopware()->Session()->sUserGroupData = $system->sUSERGROUPDATA;
                $userData['additional']['charge_vat'] = false;
                $userData['additional']['show_net'] = false;
                Shopware()->Session()->sOutputNet = true;
            } else {
                $userData['additional']['charge_vat'] = true;
                $userData['additional']['show_net'] = !empty($system->sUSERGROUPDATA['tax']);
                Shopware()->Session()->sOutputNet = empty($system->sUSERGROUPDATA['tax']);
            }
        }

        return $userData;
    }

    /**
     * Order status is set and if necessary, a status email is sent to the user
     *
     * @param string $transactionId
     * @param int $statusId
     */
    public function setOrderStatus($transactionId, $statusId)
    {

        /**
         * TODO creates config for sending email
         */
//        $config = Shopware()->Plugins()->Frontend()->PilibabaPilipaySystem()->Config();
//        $sendStatusMail = (bool)$config->paymentStatusMail;

        $order = Shopware()->Modules()->Order();

        $sql = 'SELECT id
                FROM s_order
                WHERE transactionID=?
                    AND status>=0';
        $orderId = Shopware()->Db()->fetchOne($sql, array(
            $transactionId
        ));

        $order = Shopware()->Modules()->Order();
//        $order->setOrderStatus($orderId, $statusId, $sendStatusMail);
        $order->setOrderStatus($orderId, $statusId);
    }

    /**
     * @param $errorMessage string
     */
    public function reportError($errorMessage){
        Shopware()->PluginLogger()->info('Pilipay Error: ' . $errorMessage);
        return null;
    }

    /**
     * @return boolean whether should use HTTPS
     */
    protected function shouldUseHttps(){
        /**@var Shopware\Components\Routing\Context $context;*/
        $context = $this->get('router')->getContext();
        return $context->isAlwaysSecure() or $context->isSecure() or $this->pluginConfig->get('useHttps');
    }
}
