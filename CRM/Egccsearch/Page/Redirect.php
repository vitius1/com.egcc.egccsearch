<?php
use CRM_Egccsearch_ExtensionUtil as E;

class CRM_Egccsearch_Page_Redirect extends CRM_Core_Page {

  public function run() {
    header("Location: https://crm.egcc.eu/civicrm/contact/search/custom?csid=17&reset=1");
    die();

    parent::run();
  }

}
