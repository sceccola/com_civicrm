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

require_once 'CRM/Core/Form.php';

/**
 * form to process actions on the field aspect of Custom
 */
class CRM_Price_Form_Option extends CRM_Core_Form
{
    /**
     * the price field id saved to the session for an update
     *
     * @var int
     * @access protected
     */
    protected $_fid;

    /**
     * option value  id, used when editing the Option
     *
     * @var int
     * @access protected
     */
    protected $_oid;

    /**
     * Function to set variables up before form is built
     * 
     * @param null
     * 
     * @return void
     * @access public
     */
    public function preProcess()
    { 
        
        $this->_fid  = CRM_Utils_Request::retrieve('fid', 'Positive',
                                                   $this);
        $this->_oid  = CRM_Utils_Request::retrieve('oid' , 'Positive',
                                                   $this);
        
     }

    /**
     * This function sets the default values for the form. Note that in edit/view mode
     * the default values are retrieved from the database
     * 
     * @param null
     * 
     * @return array   array of default values
     * @access public
     */
    function setDefaultValues()
    {
        $defaults = array();
        
        require_once 'CRM/Price/BAO/FieldValue.php';
        if (isset($this->_oid)) {
            $params = array('id' =>  $this->_oid );
            
            CRM_Price_BAO_FieldValue::retrieve( $params, $defaults );

            // fix the display of the monetary value, CRM-4038
            require_once 'CRM/Utils/Money.php';
            $defaults['value'] = CRM_Utils_Money::format( CRM_Utils_Array::value( 'value', $defaults ), null, '%a');
        }
        
        require_once 'CRM/Core/DAO.php';
        require_once 'CRM/Utils/Weight.php';
       
        if (! isset($defaults['weight']) || ! $defaults['weight']) {
            $fieldValues = array( 'price_field_id' => $this->_fid );
            $defaults['weight'] = CRM_Utils_Weight::getDefaultWeight('CRM_Price_DAO_FieldValue', $fieldValues);
            $defaults['is_active'] = 1;
        }

        return $defaults;
       
    }
    
    /**
     * Function to actually build the form
     * 
     * @param null
     * 
     * @return void
     * @access public
     */
    public function buildQuickForm()
    {
        if ($this->_action == CRM_Core_Action::DELETE) {
            $this->addButtons( array(
                                     array ( 'type'      => 'next',
                                             'name'      => ts('Delete') ),
                                     array ( 'type'      => 'cancel',
                                             'name'      => ts('Cancel') ),
                                     )
                               );
            return;
        } else {
            // lets trim all the whitespace
            $this->applyFilter('__ALL__', 'trim');
            
            // hidden Option Id for validation use
            $this->add('hidden', 'optionId', $this->_oid);
            
            //hidden field ID for validation use
            $this->add('hidden', 'fieldId', $this->_fid); 
            
            // label
            $this->add('text', 'label', ts('Option Label'),null, true);
            
            // FIX ME: duplicate rule?
            /*
            $this->addRule( 'label',
                            ts('Duplicate option label.'),
                            'optionExists',
                            array( 'CRM_Core_DAO_OptionValue', $this->_oid, $this->_ogId, 'label' ) );
            */

            // value
            $this->add('text', 'amount', ts('Option Amount'),null, true);
                      
            // the above value is used directly by QF, so the value has to be have a rule
            // please check with Lobo before u comment this
            $this->registerRule( 'amount', 'callback', 'money', 'CRM_Utils_Rule' );
            $this->addRule('amount', ts('Please enter a monetary value for this field.'), 'money');

            $this->add( 'textarea', 'description', ts('Description') );
 
            // count
            $this->add('text', 'count', ts('Participants Count') );
            $this->addRule('count' , ts('Please enter a valid Max Participants.'), 'positiveInteger');

            $this->add('text', 'max_value', ts('Max Participants') );
            $this->addRule('max_value' , ts('Please enter a valid Max Participants.'), 'positiveInteger');

            // weight
            $this->add('text', 'weight', ts('Order'), null, true);
            $this->addRule('weight', ts('is a numeric field') , 'numeric');
            
            // is active ?
            $this->add('checkbox', 'is_active', ts('Active?'));

            //is default 
            $this->add('checkbox', 'is_default', ts('Default'));
            
            if ( $this->_fid ) {
                //hide the default checkbox option for text field
                $htmlType = CRM_Core_DAO::getFieldValue( 'CRM_Price_BAO_Field', $this->_fid, 'html_type' );
                $this->assign( 'hideDefaultOption', false );
                if ( $htmlType == 'Text' ) {
                    $this->assign( 'hideDefaultOption', true );
                }
            }
            // add buttons
            $this->addButtons(array(
                                    array ('type'      => 'next',
                                           'name'      => ts('Save') ),
                                    array ('type'      => 'cancel',
                                           'name'      => ts('Cancel')),
                                    )
                              );
            
            // if view mode pls freeze it with the done button.
            if ($this->_action & CRM_Core_Action::VIEW) {
                $this->freeze();
                $this->addButtons(array(
                                        array ('type'      => 'cancel',
                                               'name'      => ts('Done with Preview'),
                                               'isDefault' => true),
                                        )
                                  );
            }
        }
        
        $this->addFormRule( array( 'CRM_Price_Form_Option', 'formRule' ), $this );
    }
    
    /**
     * global validation rules for the form
     *
     * @param array  $fields   (referance) posted values of the form
     *
     * @return array    if errors then list of errors to be posted back to the form,
     *                  true otherwise
     * @static
     * @access public
     */
    static function formRule( $fields, $files, $form ) 
    {
        $errors = array( );
        if ( $fields['count'] && $fields['max_value'] &&
             $fields['count'] > $fields['max_value'] ) {
            $errors['count'] = ts( 'Participant count can not be greater than max participants.' );
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Process the form
     * 
     * @param null
     * 
     * @return void
     * @access public
     */
    public function postProcess()
    {
        require_once 'CRM/Price/BAO/FieldValue.php';
        if ( $this->_action == CRM_Core_Action::DELETE ) {
            $fieldValues = array( 'price_field_id' => $this->_fid );
            $wt    = CRM_Utils_Weight::delWeight('CRM_Price_DAO_FieldValue', $this->_oid, $fieldValues);
            $label = CRM_Core_DAO::getFieldValue( "CRM_Price_DAO_FieldValue",
                                                  $this->_oid,
                                                  'label', 'id' );
   
            if ( CRM_Price_BAO_FieldValue::del($this->_oid) ) {
                CRM_Core_Session::setStatus( ts('%1 option has been deleted.', array(1 => $label)) );
            }
            return;
        } else {
            $params = $ids = array( );
            $params = $this->controller->exportValues( 'Option' );
            $fieldLabel = CRM_Core_DAO::getFieldValue( 'CRM_Price_DAO_Field', $this->_fid, 'label') ;

            $params['amount']         = CRM_Utils_Rule::cleanMoney( trim($params['amount']) );
            $params['price_field_id'] = $this->_fid;
            $params['is_default']     = CRM_Utils_Array::value( 'is_default', $params, false );

            $ids = array( );
            if ( $this->_oid ) {
                $ids['id'] = $this->_oid;
            }

            $optionValue = CRM_Price_BAO_FieldValue::create($params, $ids);
            
            CRM_Core_Session::setStatus( ts('The option \'%1\' has been saved.', array(1 => $params['label'])) );
        }
    }
}

