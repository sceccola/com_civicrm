<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */
require_once 'CRM/Contact/BAO/Relationship.php';
require_once 'CRM/Core/BAO/UFGroup.php';
require_once 'CRM/Member/BAO/Membership.php';

class CRM_Contribute_Form_Contribution_GiftSubscription
{
    /** 
     * Function to set variables up before form is built 
     *                                                           
     * @return void 
     * @access public 
     */ 
    static function preProcess( &$form )
    {
        $session   = CRM_Core_Session::singleton( );
        $contactID = $session->get( 'userID' );
        
 
                        
        $form->_profileId = CRM_Core_DAO::getFieldValue( 'CRM_Core_DAO_UFGroup', 'gift_subscription',
                                                         'id', 'name' );
        $form->assign( 'profileId', $form->_profileId );
 
        if ( $contactID ) {
            
              $form->assign( 'onBehalfRequired', $form->_onBehalfRequired );
            }
                                   
    }

    /**
     * Function to build form for related contacts / on behalf of organization.
     * 
     * @param $form              object  invoking Object
     * @param $contactType       string  contact type
     * @param $title             string  fieldset title
     *
     * @static
     */
    static function buildQuickForm( &$form ) 
    {
        $form->assign( 'fieldSetTitle', ts('Gift Subscription Details') );
        $form->assign( 'buildOnBehalfForm', true );
                
        $session   = CRM_Core_Session::singleton( );
        $contactID = $session->get( 'userID' );
      
        
        $profileFields = CRM_Core_BAO_UFGroup::getFields( $form->_profileId, false, CRM_Core_Action::VIEW, null,
                                                          null, false, null, false, null, 
                                                          CRM_Core_Permission::CREATE, null );
                                                          
        $fieldTypes = array( 'Contact', 'Individual' );
        if ( is_array( $form->_membershipBlock ) && !empty( $form->_membershipBlock ) ) {
            $fieldTypes = array_merge( $fieldTypes, array( 'Membership' ) );
        } else {
            $fieldTypes = array_merge( $fieldTypes, array( 'Contribution' ) );
        }
        
        $stateCountryMap = array( );
        $cnt = 0;
        foreach ( $profileFields as $name => $field ) {
            if ( in_array( $field['field_type'], $fieldTypes ) ) {
                list( $prefixName, $index ) = CRM_Utils_System::explode( '-', $name, 2 );
                if ( $prefixName == 'state_province' || $prefixName == 'country' || $prefixName == 'county' ) {
                    if ( ! array_key_exists( $index, $stateCountryMap ) ) {
                        $stateCountryMap[$index] = array( );
                    }
                    $stateCountryMap[$index][$prefixName] = 'onbehalf_' . $name;
                }
                CRM_Core_BAO_UFGroup::buildProfile( $form, $field, null, null, false, true );
            }
        }
        
        if ( !empty($stateCountryMap) ) {
            require_once 'CRM/Core/BAO/Address.php';
            CRM_Core_BAO_Address::addStateCountryMap( $stateCountryMap );
        }
        $form->assign('onBehalfOfFields', $profileFields);
        $form->addElement( 'hidden', 'hidden_onbehalf_profile', 1 );
    }
}