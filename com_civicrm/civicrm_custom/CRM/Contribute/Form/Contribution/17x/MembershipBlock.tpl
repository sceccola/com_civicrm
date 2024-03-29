{*
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
*}
{if $membershipBlock}
<div id="membership" class="crm-group membership-group">
  {if $context EQ "makeContribution"}
  <fieldset>    
      {if $renewal_mode }
        {if $membershipBlock.renewal_title}
            <legend>{$membershipBlock.renewal_title}</legend>
        {/if}
        {if $membershipBlock.renewal_text}
            <div id="membership-intro" class="crm-section membership_renewal_intro-section">
                <p>{$membershipBlock.renewal_text}</p>
            </div> 
        {/if}

      {else}        
        {if $membershipBlock.new_title}
            <legend>{$membershipBlock.new_title}</legend>
        {/if}
        {if $membershipBlock.new_text}
            <div id="membership-intro" class="crm-section membership_new_intro-section">
                <p>{$membershipBlock.new_text}</p>
            </div> 
        {/if}
      {/if}
  {/if}
  {if  $context neq "makeContribution" }
        <div class="header-dark">
            {if $renewal_mode }
                    {if $membershipBlock.renewal_title}
                        {$membershipBlock.renewal_title}
                    {else}
                        {ts}Select a Membership Renewal Level{/ts}
                    {/if}

            {else}
                    {if $membershipBlock.new_title}
                        {$membershipBlock.new_title}
                    {else}
                        {ts}Select a Membership Level{/ts}
                    {/if}
            {/if}
        </div>
    {/if}
   
    {strip}
        <table id="membership-listings">
        {foreach from=$membershipTypes item=row }
        <tr {if $context EQ "makeContribution" OR $context EQ "thankContribution" }class="odd-row" {/if}valign="top">
            {if $showRadio }
                {assign var="pid" value=$row.id}
                <td style="width: 1em;">{$form.selectMembership.$pid.html}</td>
            {else}
                <td>&nbsp;</td>                
            {/if}
           <td style="width: auto;">
                <span class="bold">{$row.name} &nbsp;
                {if ($membershipBlock.display_min_fee AND $context EQ "makeContribution") AND $row.minimum_fee GT 0 }
                    {if $is_separate_payment OR ! $form.amount.label}
                        - {$row.minimum_fee|crmMoney}
                    {else}
                        {ts 1=$row.minimum_fee|crmMoney}(contribute at least %1 to be eligible for this membership){/ts}
                    {/if}
                {/if}
                </span><br />
                {$row.description} &nbsp;                      
           </td>
            
            <td style="width: auto;">
              {* Check if there is an existing membership of this type (current_membership NOT empty) and if the end-date is prior to today. *}
              {if $row.current_membership AND $context EQ "makeContribution" }
                    {if $row.current_membership|date_format:"%Y%m%d" LT $smarty.now|date_format:"%Y%m%d"}
                        <br /><em>{ts 1=$row.current_membership|crmDate 2=$row.name}Your <strong>%2</strong> membership expired on %1.{/ts}</em>
                    {else}
                        <br /><em>{ts 1=$row.current_membership|crmDate 2=$row.name}Your <strong>%2</strong> membership expires on %1.{/ts}</em>
                    {/if}
              {else}
                &nbsp;
              {/if}
           </td> 
        </tr>
	
        {/foreach}
	<tr id="allow_auto_renew">    
	     <td style="width: auto;">{$form.auto_renew.html}</td>
	     <td style="width: auto;">
	        {$form.auto_renew.label}
            <div class="description crm-auto-renew-cancel-info">({ts}Your initial membership fee will be processed once you complete the confirmation step. You will be able to cancel automatic renewals at any time by logging in to your account or contacting us.{/ts})</div>
	     </td>
	</tr>
        {if $showRadio}
            {if $showRadioNoThanks } {* Provide no-thanks option when Membership signup is not required - per membership block configuration. *}
            <tr class="odd-row">
              <td>{$form.selectMembership.no_thanks.html}</td>
              <td colspan="2"><strong>{ts}No thank you{/ts}</strong></td>      
            </tr> 
            {/if}
        {/if}          
        </table>
    {/strip}
    {if $context EQ "makeContribution"}
        </fieldset>
    {/if}
</div>

{literal}
<script type="text/javascript">
cj(function(){
   showHideAutoRenew( null );	
});
function showHideAutoRenew( memTypeId ) 
{
  var considerUserInput = {/literal}'{$takeUserSubmittedAutoRenew}'{literal};	    
  if ( memTypeId ) considerUserInput = false;
  if ( !memTypeId ) memTypeId = cj('input:radio[name="selectMembership"]:checked').val();
  
  //does this page has only one membership type.
  var singleMembership = {/literal}'{$singleMembership}'{literal};
  if ( !memTypeId && singleMembership ) memTypeId = cj("#selectMembership").val( ); 
  
  var renewOptions  = {/literal}{$autoRenewMembershipTypeOptions}{literal};	 
  var currentOption = eval( "renewOptions." + 'autoRenewMembershipType_' + memTypeId );
  
  funName = 'hide();';
  var readOnly = false;
  var isChecked  = false; 
  if ( currentOption == 1 ) {
     funName = 'show();';
     
     //uncomment me, if we'd like 
     //to load auto_renew checked.
     //isChecked = true;
  
  } else if ( currentOption == 2 ) {
     funName = 'show();';
     isChecked = readOnly = true;
  }
  
  var autoRenew = cj("#auto_renew");	
  if ( considerUserInput ) isChecked = autoRenew.attr( 'checked' ); 

  //its a normal recur contribution.
  if ( cj( "is_recur" ) && 
      ( cj( 'input:radio[name="is_recur"]:checked').val() == 1 ) ) {
     isChecked = false;
     funName   = 'hide();';
  }
 
  //when we do show auto_renew read only 
  //which implies it should be checked.	 
  if ( readOnly && funName == 'show();' ) isChecked = true; 

  autoRenew.attr( 'readonly', readOnly );
  autoRenew.attr( 'checked',  isChecked );
  eval( "cj('#allow_auto_renew')." + funName );
}

{/literal}{if $allowAutoRenewMembership}{literal}
  cj( function( ) {
     //keep read only always checked.
     cj( "#auto_renew" ).click(function( ) {
        if ( cj(this).attr( 'readonly' ) ) { 
            cj(this).attr( 'checked', true );
        }
     });
  }); 
{/literal}{/if}{literal}
</script>
{/literal}

{/if}{* membership block end here *}
