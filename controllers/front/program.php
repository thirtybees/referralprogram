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
 * Class ReferralprogramProgramModuleFrontController
 */
class ReferralprogramProgramModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * Initialize the controller
     * @throws PrestaShopException
     */
    public function init()
    {
        if (!$this->context->customer->isLogged()) {
            Tools::redirect(
                $this->context->link->getPageLink(
                    'authentication',
                    true,
                    (int) $this->context->language->id,
                    'back='.urlencode($this->context->link->getModuleLink('referralprogram', 'program'))
                )
            );
        }

        parent::init();
    }

    /**
     * Set this controller's assets
     * @throws PrestaShopException
     */
    public function setMedia()
    {
        parent::setMedia();
        $this->context->controller->addJS(_MODULE_DIR_.$this->module->name.'/js/'.$this->module->name.'.js');
        $this->addJqueryPlugin(['thickbox', 'idTabs']);
    }

    /**
     * @throws PrestaShopException
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        // get discount value (ready to display)
        $discountType = (int) (Configuration::get('REFERRAL_DISCOUNT_TYPE'));

        if ($discountType === ReferralProgram::DISCOUNT_PERCENT) {
            $discount = ReferralProgram::formatDiscount((float) (Configuration::get('REFERRAL_PERCENTAGE')), $discountType, new Currency($this->context->currency->id));
        } else {
            $discount = ReferralProgram::formatDiscount((float) (Configuration::get('REFERRAL_DISCOUNT_VALUE_'.(int) ($this->context->currency->id))), $discountType, new Currency($this->context->currency->id));
        }

        $activeTab = 'sponsor';
        $error = false;

        // Mailing invitation to friend sponsor
        $invitationSent = false;
        $nbInvitation = 0;
        if (Tools::isSubmit('submitSponsorFriends')
            && Tools::getValue('friendsEmail')
            && sizeof($friendsEmail = Tools::getValue('friendsEmail')) >= 1
        ) {
            $activeTab = 'sponsor';

            if (!Tools::getValue('conditionsValided')) {
                $error = 'conditions not valided';
            } else {
                $friendsLastName = Tools::getValue('friendsLastName');
                $friendsFirstName = Tools::getValue('friendsFirstName');
                $mailsExists = [];

                foreach ($friendsEmail as $key => $friendEmail) {
                    $friendEmail = strval($friendEmail);
                    $friendLastName = strval($friendsLastName[$key]);
                    $friendFirstName = strval($friendsFirstName[$key]);

                    if (empty($friendEmail) && empty($friendLastName) && empty($friendFirstName)) {
                        continue;
                    } elseif (empty($friendEmail) || !Validate::isEmail($friendEmail)) {
                        $error = 'email invalid';
                    } elseif (empty($friendFirstName) || empty($friendLastName) && !Validate::isName($friendLastName) && !Validate::isName($friendFirstName)) {
                        $error = 'name invalid';
                    } elseif (ReferralProgramModule::isEmailExists($friendEmail) && Customer::customerExists($friendEmail)) {
                        $mailsExists[] = $friendEmail;
                    } else {
                        $referralprogram = new ReferralProgramModule();
                        $referralprogram->id_sponsor = (int) ($this->context->customer->id);
                        $referralprogram->firstname = $friendFirstName;
                        $referralprogram->lastname = $friendLastName;
                        $referralprogram->email = $friendEmail;
                        if (!$referralprogram->validateFields(false)) {
                            $error = 'name invalid';
                        } else {
                            if ($referralprogram->save()) {
                                $cipherTool = Encryptor::getInstance();
                                $vars = [
                                    '{email}'            => strval($this->context->customer->email),
                                    '{lastname}'         => strval($this->context->customer->lastname),
                                    '{firstname}'        => strval($this->context->customer->firstname),
                                    '{email_friend}'     => $friendEmail,
                                    '{lastname_friend}'  => $friendLastName,
                                    '{firstname_friend}' => $friendFirstName,
                                    '{link}'             => Context::getContext()->link->getPageLink('authentication', true, Context::getContext()->language->id, 'create_account=1&sponsor='.urlencode($cipherTool->encrypt($referralprogram->id.'|'.$referralprogram->email.'|')).'&back=my-account', false),
                                    '{discount}'         => $discount,
                                ];
                                Mail::Send((int) $this->context->language->id, 'referralprogram-invitation', Mail::l('Referral Program', (int) $this->context->language->id), $vars, $friendEmail, $friendFirstName.' '.$friendLastName, strval(Configuration::get('PS_SHOP_EMAIL')), strval(Configuration::get('PS_SHOP_NAME')), null, null, dirname(__FILE__).'/../../mails/');
                                $invitationSent = true;
                                $nbInvitation++;
                                $activeTab = 'pending';
                            } else {
                                $error = 'cannot add friends';
                            }
                        }
                    }

                    if ($error) {
                        break;
                    }
                }

                if ($nbInvitation > 0) {
                    unset($_POST);
                }

                //Not to stop the sending of e-mails in case of doubloon
                if (sizeof($mailsExists)) {
                    $error = 'email exists';
                }
            }
        }

        // Mailing revive
        $reviveSent = false;
        $nbRevive = 0;

        if (Tools::isSubmit('revive')) {
            $activeTab = 'pending';
            if (Tools::getValue('friendChecked') && sizeof($friendsChecked = Tools::getValue('friendChecked')) >= 1) {
                foreach ($friendsChecked as $key => $friendChecked) {
                    if (ReferralProgramModule::isSponsorFriend((int) ($this->context->customer->id), (int) ($key))) {
                        $cipherTool = Encryptor::getInstance();
                        $referralprogram = new ReferralProgramModule((int) ($key));
                        $vars = [
                            '{email}'            => $this->context->customer->email,
                            '{lastname}'         => $this->context->customer->lastname,
                            '{firstname}'        => $this->context->customer->firstname,
                            '{email_friend}'     => $referralprogram->email,
                            '{lastname_friend}'  => $referralprogram->lastname,
                            '{firstname_friend}' => $referralprogram->firstname,
                            '{link}'             => Context::getContext()->link->getPageLink('authentication', true, Context::getContext()->language->id, 'create_account=1&sponsor='.urlencode($cipherTool->encrypt($referralprogram->id.'|'.$referralprogram->email.'|')), false),
                            '{discount}'         => $discount,
                        ];
                        $referralprogram->save();
                        Mail::Send((int) $this->context->language->id, 'referralprogram-invitation', Mail::l('Referral Program', (int) $this->context->language->id), $vars, $referralprogram->email, $referralprogram->firstname.' '.$referralprogram->lastname, strval(Configuration::get('PS_SHOP_EMAIL')), strval(Configuration::get('PS_SHOP_NAME')), null, null, dirname(__FILE__).'/../../mails/');
                        $reviveSent = true;
                        $nbRevive++;
                    }
                }
            } else {
                $error = 'no revive checked';
            }
        }

        $customer = new Customer((int) ($this->context->customer->id));
        $stats = $customer->getStats();

        $orderQuantity = (int) (Configuration::get('REFERRAL_ORDER_QUANTITY'));
        $canSendInvitations = false;

        if ((int) ($stats['nb_orders']) >= $orderQuantity) {
            $canSendInvitations = true;
        }

        $discountInPercent = Tools::getValue('discount_type', Configuration::get('REFERRAL_DISCOUNT_TYPE')) == 1;

        // Smarty display
        $this->context->smarty->assign(
            [
                'activeTab'          => $activeTab,
                'discount'           => $discount,
                'orderQuantity'      => $orderQuantity,
                'canSendInvitations' => $canSendInvitations,
                'nbFriends'          => (int) (Configuration::get('REFERRAL_NB_FRIENDS')),
                'error'              => $error,
                'invitation_sent'    => $invitationSent,
                'nbInvitation'       => $nbInvitation,
                'pendingFriends'     => ReferralProgramModule::getSponsorFriend((int) ($this->context->customer->id), 'pending'),
                'revive_sent'        => $reviveSent,
                'nbRevive'           => $nbRevive,
                'subscribeFriends'   => ReferralProgramModule::getSponsorFriend((int) ($this->context->customer->id), 'subscribed'),
                'mails_exists'       => (isset($mailsExists) ? $mailsExists : []),
                'currencySign'       => ($discountInPercent ? '%' : $this->context->currency->sign),
            ]
        );
        $this->setTemplate('program.tpl');
    }
}
