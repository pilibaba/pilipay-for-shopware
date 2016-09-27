<?php

/*
 * (c) PILIBABA INTERNATIONAL CO.,LTD. <info@pilibaba.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\CustomModels\PilipayWarehouses;

use Shopware\Components\Model\ModelEntity,
    Doctrine\ORM\Mapping AS ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="pilipay_warehouses")
 */
class PilipayWarehouses extends ModelEntity {

    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", nullable=false)
     */
    private $name;

    /**
     * Flag, which shows if the warehouse is active or not. 1= active otherwise inactive
     *
     * @var boolean $active
     * @ORM\Column(name="active", type="boolean", nullable=false)
     */
    private $active;

    /**
     * @var string $receiverFirstName
     * @ORM\Column(name="receiverFirstName", type="string", nullable=false)
     */
    private $receiverFirstName;

    /**
     * @var string $receiverLastName
     * @ORM\Column(name="receiverLastName", type="string", nullable=false)
     */
    private $receiverLastName;

    /**
     * @var string $receiverPhone
     * @ORM\Column(name="receiverPhone", type="string", nullable=false)
     */
    private $receiverPhone;
    
    /**
     * @var string $street
     * @ORM\Column(name="street", type="string", nullable=false)
     */
    private $street;

    /**
     * @var string $addressLine1
     * @ORM\Column(name="addressLine1", type="string", nullable=false)
     */
    private $addressLine1;

    /**
     * @var string $addressLine2
     * @ORM\Column(name="addressLine2", type="string", nullable=false)
     */
    private $addressLine2;

    /**
     * @var string $zipCode
     * @ORM\Column(name="zipCode", type="string", nullable=false)
     */
    private $zipCode;

    /**
     * @var string $city
     * @ORM\Column(name="city", type="string", nullable=false)
     */
    private $city;

    /**
     * @var string $state
     * @ORM\Column(name="state", type="string", nullable=false)
     */
    private $state;

    /**
     * @var string $country
     * @ORM\Column(name="country", type="string", nullable=false)
     */
    private $country;

    /**
     * @var string $countryIsoCode
     * @ORM\Column(name="countryIsoCode", type="string", nullable=false)
     */
    private $countryIsoCode;

    /**
     * @var string $company
     * @ORM\Column(name="company", type="string", nullable=false)
     */
    private $company;

    /**
     * Get Id
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * Set Name
     *
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
        return $this;
    }

    /**
     * Get Name
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Set Active
     *
     * @param boolean $active
     */
    public function setActive($active) {

        $builder = Shopware()->Models()->createQueryBuilder();
        $q = $builder->update('Shopware\CustomModels\PilipayWarehouses\PilipayWarehouses', 'pw')
                ->set('pw.active', 0)
                ->getQuery();

        $q->execute();

        $this->active = $active;
        return $this;
    }

    /**
     * Get Active
     *
     * @return boolean
     */
    public function getActive() {
        return $this->active;
    }

    /**
     * @return string
     */
    public function getReceiverFirstName()
    {
        return $this->receiverFirstName;
    }

    /**
     * @param string $receiverFirstName
     */
    public function setReceiverFirstName($receiverFirstName)
    {
        $this->receiverFirstName = $receiverFirstName;
    }

    /**
     * @return string
     */
    public function getReceiverLastName()
    {
        return $this->receiverLastName;
    }

    /**
     * @param string $receiverLastName
     */
    public function setReceiverLastName($receiverLastName)
    {
        $this->receiverLastName = $receiverLastName;
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @param string $street
     */
    public function setStreet($street)
    {
        $this->street = $street;
    }
    
    /**
     * @return string
     */
    public function getAddressLine1()
    {
        return $this->addressLine1;
    }

    /**
     * @param string $addressLine1
     */
    public function setAddressLine1($addressLine1)
    {
        $this->addressLine1 = $addressLine1;
    }

    /**
     * @return string
     */
    public function getAddressLine2()
    {
        return $this->addressLine2;
    }

    /**
     * @param string $addressLine2
     */
    public function setAddressLine2($addressLine2)
    {
        $this->addressLine2 = $addressLine2;
    }

    /**
     * @return string
     */
    public function getZipCode()
    {
        return $this->zipCode;
    }

    /**
     * @param string $zipCode
     */
    public function setZipCode($zipCode)
    {
        $this->zipCode = $zipCode;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param string $city
     */
    public function setCity($city)
    {
        $this->city = $city;
    }

    /**
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }

    /**
     * @return string
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * @param string $company
     */
    public function setCompany($company)
    {
        $this->company = $company;
    }

    /**
     * @return string
     */
    public function getReceiverPhone()
    {
        return $this->receiverPhone;
    }

    /**
     * @param string $receiverPhone
     */
    public function setReceiverPhone($receiverPhone)
    {
        $this->receiverPhone = $receiverPhone;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $state
     */
    public function setState($state)
    {
        $this->state = $state;
    }

    /**
     * @return string
     */
    public function getCountryIsoCode()
    {
        return $this->countryIsoCode;
    }

    /**
     * @param string $countryIsoCode
     */
    public function setCountryIsoCode($countryIsoCode)
    {
        $this->countryIsoCode = $countryIsoCode;
    }

}
