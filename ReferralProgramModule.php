<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class ReferralProgramModule
 */
class ReferralProgramModule extends ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'referralprogram',
        'primary' => 'id_referralprogram',
        'fields'  => [
            'id_sponsor'           => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'email'                => ['type' => self::TYPE_STRING, 'validate' => 'isEmail',      'required' => true, 'size' => 255],
            'lastname'             => ['type' => self::TYPE_STRING, 'validate' => 'isName',       'required' => true, 'size' => 128],
            'firstname'            => ['type' => self::TYPE_STRING, 'validate' => 'isName',       'required' => true, 'size' => 128],
            'id_customer'          => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'id_cart_rule'         => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'id_cart_rule_sponsor' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'date_add'             => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'             => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];
    public $id_sponsor;
    public $email;
    public $lastname;
    public $firstname;
    public $id_customer;
    public $id_cart_rule;
    public $id_cart_rule_sponsor;
    public $date_add;
    public $date_upd;
    // @codingStandardsIgnoreEnd

    /**
     * Return sponsored friends
     *
     * @param int  $idCustomer
     * @param bool $restriction
     *
     * @return array Sponsor
     */
    public static function getSponsorFriend($idCustomer, $restriction = false)
    {
        if (!(int) ($idCustomer)) {
            return [];
        }

        $query = (new DbQuery())
            ->select('s.*')
            ->from(bqSQL(static::$definition['table']), 's')
            ->where('s.`id_sponsor` - '.(int) $idCustomer);
        if ($restriction) {
            if ($restriction === 'pending') {
                $query->where('s.`id_customer` = 0');
            } elseif ($restriction === 'subscribed') {
                $query->where('s.`id_customer` != 0');
            }
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);
    }

    /**
     * Return if a customer is sponsored
     *
     * @param int  $idCustomer
     * @param bool $getId
     *
     * @return bool
     */
    public static function isSponsorised($idCustomer, $getId = false)
    {
        $result = Db::getInstance()->getRow(
            '
		SELECT s.`id_referralprogram`
		FROM `'._DB_PREFIX_.'referralprogram` s
		WHERE s.`id_customer` = '.(int) $idCustomer
        );

        if (isset($result[static::$definition['primary']]) && $getId === true) {
            return (int) $result[static::$definition['primary']];
        }

        return isset($result[static::$definition['primary']]);
    }

    /**
     * Is friend a sponsor friend?
     *
     * @param int $idSponsor
     * @param int $idFriend
     *
     * @return bool
     */
    public static function isSponsorFriend($idSponsor, $idFriend)
    {
        if (!(int) ($idSponsor) || !(int) ($idFriend)) {
            return false;
        }

        return (bool) Db::getInstance()->getValue(
            (new DbQuery())
            ->select('s.`'.bqSQL(static::$definition['primary']).'`')
            ->from(bqSQL(static::$definition['table']), 's')
            ->where('s.`id_sponsor` = '.(int) $idSponsor)
            ->where('s.`'.bqSQL(static::$definition['table']).'` = '.(int) $idFriend)
        );
    }

    /**
     * Return if an email is already registered
     *
     * @param string $email
     * @param bool   $getId
     * @param bool   $checkCustomer
     *
     * @return false|int Referral program ID
     */
    public static function isEmailExists($email, $getId = false, $checkCustomer = true)
    {
        if (empty($email) || !Validate::isEmail($email)) {
            die (Tools::displayError('The email address is invalid.'));
        }

        if ($checkCustomer === true && Customer::customerExists($email)) {
            return false;
        }

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new DbQuery())
            ->select('s.`'.bqSQL(static::$definition['primary']).'`')
            ->from(bqSQL(static::$definition['table']), 's')
            ->where('s.`email` = \''.pSQL($email).'\'')
        );
        if ($getId) {
            return (int) $result[static::$definition['primary']];
        }

        return isset($result[static::$definition['primary']]);
    }

    /**
     * Register discount for sponsor
     *
     * @param int $idCurrency
     *
     * @return bool
     */
    public function registerDiscountForSponsor($idCurrency)
    {
        if ((int) $this->id_cart_rule_sponsor > 0) {
            return false;
        }

        return $this->registerDiscount((int) $this->id_sponsor, 'sponsor', (int) $idCurrency);
    }

    /**
     * Register discount
     *
     * @param int  $idCustomer
     * @param bool $register
     * @param int  $idCurrency
     *
     * @return bool
     */
    public function registerDiscount($idCustomer, $register = false, $idCurrency = 0)
    {
        $configurations = Configuration::getMultiple(['REFERRAL_DISCOUNT_TYPE', 'REFERRAL_PERCENTAGE', 'REFERRAL_DISCOUNT_CUMULATIVE', 'REFERRAL_DISCOUNT_VALUE_'.(int) $idCurrency, 'REFERRAL_TAX']);

        $cartRule = new CartRule();
        if ((int) $configurations['REFERRAL_DISCOUNT_TYPE'] === ReferralProgram::DISCOUNT_PERCENT) {
            $cartRule->reduction_percent = (float) $configurations['REFERRAL_PERCENTAGE'];
        } elseif ((int) $configurations['REFERRAL_DISCOUNT_TYPE'] === ReferralProgram::DISCOUNT_AMOUNT && isset($configurations['REFERRAL_DISCOUNT_VALUE_'.(int) $idCurrency])) {
            $cartRule->reduction_amount = (float) $configurations['REFERRAL_DISCOUNT_VALUE_'.(int) $idCurrency];
            $cartRule->reduction_tax = (int) $configurations['REFERRAL_TAX'];
        }

        $cartRule->cart_rule_restriction = !(int) $configurations['REFERRAL_DISCOUNT_CUMULATIVE'];
        $cartRule->quantity = 1;
        $cartRule->quantity_per_user = 1;
        $cartRule->date_from = date('Y-m-d H:i:s', time());
        $cartRule->date_to = date('Y-m-d H:i:s', time() + 31536000); // + 1 year
        $cartRule->code = $this->getDiscountPrefix().Tools::passwdGen(6);
        $cartRule->name = Configuration::getInt('REFERRAL_DISCOUNT_DESCRIPTION');
        if (empty($cartRule->name)) {
            $cartRule->name = 'Referral reward';
        }
        $cartRule->id_customer = (int) $idCustomer;
        $cartRule->reduction_currency = (int) $idCurrency;

        if ($cartRule->add()) {
            if ($register != false) {
                if ($register == 'sponsor') {
                    $this->id_cart_rule_sponsor = (int) $cartRule->id;
                } elseif ($register == 'sponsored') {
                    $this->id_cart_rule = (int) $cartRule->id;
                }

                return $this->save();
            }

            return true;
        }

        return false;
    }

    /**
     * Get discount prefix
     *
     * @return string
     */
    public static function getDiscountPrefix()
    {
        return 'SP';
    }

    /**
     * Register discount for sponsored customer
     *
     * @param int $idCurrency
     *
     * @return bool
     */
    public function registerDiscountForSponsored($idCurrency)
    {
        if (!(int) $this->id_customer || (int) $this->id_cart_rule > 0) {
            return false;
        }

        return $this->registerDiscount((int) $this->id_customer, 'sponsored', (int) $idCurrency);
    }
}
