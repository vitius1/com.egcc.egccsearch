<?php

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */
class CRM_Contact_Form_Search_Custom_EgccSearch extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {
  protected $_formValues;
  protected $_aclFrom = NULL;
  protected $_aclWhere = NULL;
  public $_permissionedComponent;

  public function __construct(&$formValues) {
    $this->_formValues = $formValues;

    // Define the columns for search result rows
    $this->_columns = array(
      ts('Name') => 'sort_name',
      ts('Email') => 'email',
      ts('Country') => 'country',
    );
  }



  public function GetRelationship() {
    $result = civicrm_api3('RelationshipType', 'get', [
      'sequential' => 1,
      'return' => ["label_a_b","label_b_a"],
      'is_active' => 1,
      'options' => ['limit' => 0, 'sort' => "label_a_b"],
    ]);

    $relationship = ['' => ts('- any relationship -')];
    foreach ($result["values"] as $value) {
        $relationship+=[$value["id"] => $value["label_a_b"]." / ".$value["label_b_a"]];
    }

    return $relationship;
  }

  public function GetEvent() {
    $result = civicrm_api3('Event', 'get', [
      'sequential' => 1,
      'return' => ["event_title"],
      'is_active' => 1,
      'options' => ['limit' => 0, 'sort' => "event_title"],
    ]);

    $event = [];
    foreach ($result["values"] as $value) {
        $event+=[$value["id"] => $value["event_title"]];
    }

    return $event;
  }

  public function GetTag() {
    $result = civicrm_api3('Tag', 'get', [
      'sequential' => 1,
      'return' => ["name"],
      'is_active' => 1,
      'options' => ['limit' => 0, 'sort' => "name"],
    ]);

    $tag = [];
    foreach ($result["values"] as $value) {
        $tag+=[$value["id"] => $value["name"]];
    }

    return $tag;
  }

  public function GetCountry() {
    $result = civicrm_api3('Country', 'get', [
      'sequential' => 1,
      'return' => ["name"],
      'is_active' => 1,
      'options' => ['limit' => 0, 'sort' => "name"],
    ]);

    $country = ['' => ts('- any country -')];
    foreach ($result["values"] as $value) {
        $country+=[$value["id"] => $value["name"]];
    }

    return $country;
  }


  /**
   * Build the form.
   *
   * The form consists of an autocomplete field to select an organization.
   */
  public function buildForm(&$form) {
    $andOr = [
      '1' => ts('AND'),
      '0' => ts('OR')
    ];
    $and = ['0' => ts('OR')];
    // add select for groups
    $group = CRM_Core_PseudoConstant::nestedGroup();
    $form->addElement('text', 'sort_name', ts('Name or email'));
    $form->addElement('select', 'country', ts('Country'), $this->GetCountry(), ['class' => 'crm-select2 huge', 'onChange'=>'CountryChange(this.value)']);
    $form->addRadio('countryRadio', ts(''), $and, [], ['class' => 'crm-form-radio huge']);
    $form->setDefaults(array('countryRadio'=>'0'));

    $form->addElement('select', 'kraj', ts('State province'), [], ['class' => 'crm-select2 huge','multiple' => 'multiple','placeholder' => '- choose country first -', 'disabled']);
    $form->addRadio('krajRadio', ts(''), $andOr, [], ['class' => 'crm-form-radio huge']);
    $form->setDefaults(array('krajRadio'=>'1'));

    $form->addElement('select', 'group', ts('Group'), $group, ['class' => 'crm-select2 huge','multiple' => 'multiple','placeholder' => '- any group -']);
    $form->addRadio('groupRadio', ts(''), $andOr, [], ['class' => 'crm-form-radio huge']);
    $form->setDefaults(array('groupRadio'=>'1'));
    //$form->addElement('select', 'relationship', ts('Having this relationship'), $this->GetRelationship(), ['class' => 'crm-select2 huge']);

    $form->addElement('select', 'event', ts('Event'), $this->GetEvent(), ['class' => 'crm-select2 huge','multiple' => 'multiple','placeholder' => '- any event -']);
    $form->addRadio('eventRadio', ts(''), $andOr, [], ['class' => 'crm-form-radio huge']);
    $form->setDefaults(array('eventRadio'=>'1'));

    $form->addElement('select', 'tag', ts('Tag'), $this->GetTag(), ['class' => 'crm-select2 huge','multiple' => 'multiple','placeholder' => '- any tag -']);
    $form->addRadio('tagRadio', ts(''), $andOr, [], ['class' => 'crm-form-radio huge']);
    $form->setDefaults(array('tagRadio'=>'1'));

    $form->addElement('hidden', 'id');

    $this->setTitle('Search');

    $form->assign('elements', array('sort_name', 'country', 'countryRadio', 'kraj', 'krajRadio', 'email', 'group', 'groupRadio', 'event', 'eventRadio', 'tag', 'tagRadio', 'id'));
  }

  /**
   * Define the smarty template used to layout the search form and results
   * listings.
   */
  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom/EgccSearch.tpl';
  }

  /**
   * Construct the search query.
   */
  public function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    if ($justIDs) {
      $select = "c.id as contact_id";
    }
    else {
      $select = "c.id as contact_id, c.sort_name as sort_name, e.email as email, CONCAT_WS(' - ',co.name ,pr.name) as country, c.id as id2";
    }

    $from = $this->from();

    $where = $this->where($includeContactIDs);

    // Define GROUP BY here if needed.
    $grouping = "c.id, c.sort_name, e.email, CONCAT_WS(' - ',co.name ,pr.name)";

    $sql = "
            SELECT $select
            FROM   $from
            WHERE  $where
            GROUP BY $grouping
            ";
    // Define ORDER BY for query in $sort, with default value
    if (!empty($sort)) {
      if (is_string($sort)) {
        $sort = CRM_Utils_Type::escape($sort, 'String');
        $sql .= " ORDER BY $sort ";
      }
      else {
        $sql .= " ORDER BY " . trim($sort->orderBy());
      }
    }
    else {
      $sql .= "ORDER BY sort_name asc";
    }

    /* Uncomment the next 2 lines to see the exact query you're generating */

    // CRM_Core_Error::debug('sql',$sql);
    // exit();

    return $sql;
  }

  /**
   * Returns the join of tables on which to query.
   */
  public function from() {
    return "civicrm_contact c
            left join civicrm_email e ON c.id = e.contact_id
            left join civicrm_address a on c.id = a.contact_id and a.is_primary=1
            left join civicrm_country co on a.country_id = co.id
            left join civicrm_state_province pr on a.state_province_id = pr.id
            left join civicrm_group_contact g on c.id = g.contact_id
            left join civicrm_relationship r1 on c.id = r1.contact_id_a
            left join civicrm_relationship r2 on c.id = r2.contact_id_b
            left join civicrm_participant p on c.id = p.contact_id
            left join civicrm_event ev on p.event_id = ev.id
            left join civicrm_entity_tag t on c.id = t.entity_id";
  }

  /**
   * Returns the where clause.
   */
  public function where($includeContactIDs = FALSE) {
    $clauses = array();

    $name = $this->_formValues['sort_name'];
    $country = $this->_formValues['country'];
    $province = $this->_formValues['kraj'];
    $group = $this->_formValues['group'];
    $relationship = $this->_formValues['relationship'];
    $event = $this->_formValues['event'];
    $tag = $this->_formValues['tag'];
    $provinceRadio = $this->_formValues['krajRadio'];
    $groupRadio = $this->_formValues['groupRadio'];
    $eventRadio = $this->_formValues['eventRadio'];
    $tagRadio = $this->_formValues['tagRadio'];

    $names=explode(" ", $name);
    if(count($names) == 2) {
      $clauses[] = "(c.sort_name LIKE '%{$name}%' OR c.display_name LIKE '%{$name}%' OR e.email LIKE '%{$name}%'
      OR (c.first_name LIKE '%{$names[0]}%' AND c.last_name LIKE '%{$names[1]}%')
      OR (c.first_name LIKE '%{$names[1]}%' AND c.last_name LIKE '%{$names[0]}%'))";
    } else {
      $clauses[] = "(c.sort_name LIKE '%{$name}%' OR c.display_name LIKE '%{$name}%' OR e.email LIKE '%{$name}%')";
    }

    if($country != "") {
      $clauses[] = "a.country_id = {$country}";
    }

    if($province[0] != "") {
      $pom=[];
      foreach ($province as $value) {
        $pom[]="a.state_province_id = {$value}";
      }
      if($provinceRadio==1){
        $pom2=implode(' AND ', $pom);
        $clauses[]=$pom2;
      } elseif($provinceRadio==0){
        $pom2=implode(' OR ', $pom);
        $clauses[]="(".$pom2.")";
      }
    }


    if($group[0] != "") {
      $pom=[];
      foreach ($group as $g) {
        $result = civicrm_api3('Group', 'getsingle', [
          'return' => ["where_clause"],
          'id' => $g,
        ]);
        $pom[]="(c.id in (select contact_id
                    from civicrm_group_contact
                    where group_id = {$g})
                    OR
                    c.id in (select contact_id
                    from civicrm_group_contact_cache
                    where group_id = {$g}))";
      }
      if($groupRadio==1){
        $pom2=implode(' AND ', $pom);
        $clauses[]=$pom2;
      } elseif($groupRadio==0){
        $pom2=implode(' OR ', $pom);
        $clauses[]="(".$pom2.")";
      }
    }

    if($tag[0] != "") {
      $pom=[];
      foreach ($tag as $t) {
        $pom[]="c.id in (select entity_id
                    from civicrm_entity_tag
                    where tag_id = {$t})";
      }
      if($tagRadio==1){
        $pom2=implode(' AND ', $pom);
        $clauses[]=$pom2;
      } elseif($tagRadio==0){
        $pom2=implode(' OR ', $pom);
        $clauses[]="(".$pom2.")";
      }
    }

    /*
    if($relationship != "") {
      $date=date("Y-m-d");
      $clauses[] = "(
        (r1.relationship_type_id = {$relationship} AND
          (r1.end_date is null OR r1.end_date <= '{$date}') AND
          (r1.start_date is null OR r1.start_date >= '{$date}'))
        OR ((r2.relationship_type_id = {$relationship}) AND
          (r2.end_date is null OR r2.end_date <= '{$date}') AND
          (r2.start_date is null OR r2.start_date >= '{$date}'))
      )";
    }
    */

    if($event[0] != "") {
      $pom=[];
      foreach ($event as $e) {
        $pom[]="c.id in (select contact_id
                    from civicrm_participant
                    where event_id = {$e} AND status_id=2)";
      }
      if($eventRadio==1){
        $pom2=implode(' AND ', $pom);
        $clauses[]=$pom2;
      } elseif($eventRadio==0){
        $pom2=implode(' OR ', $pom);
        $clauses[]="(".$pom2.")";
      }
    }


    $clauses[] = "e.is_primary = 1";


    // This if-structure was copied from another search.
    // Not sure what it is supposed to do.
    if ($includeContactIDs) {
      $contactIDs = array();
      foreach ($this->_formValues as $id => $value) {
        if ($value &&
            substr($id, 0, CRM_Core_Form::CB_PREFIX_LEN) == CRM_Core_Form::CB_PREFIX
        ) {
          $contactIDs[] = substr($id, CRM_Core_Form::CB_PREFIX_LEN);
        }
      }

      if (!empty($contactIDs)) {
        $contactIDs = implode(', ', $contactIDs);
        $clauses[] = "contact.id IN ( $contactIDs )";
      }
    }

    return implode(' AND ', $clauses);
  }

  /*
   * Functions below generally don't need to be modified
   */

  public function count() {
    $sql = $this->all();

    $dao = CRM_Core_DAO::executeQuery($sql, CRM_Core_DAO::$_nullArray
    );
    return $dao->N;
  }

  public function contactIDs($offset = 0, $rowcount = 0, $sort = NULL) {
    return $this->all($offset, $rowcount, $sort);
  }

  public function &columns() {
    return $this->_columns;
  }

  public function setTitle($title) {
    if ($title) {
      CRM_Utils_System::setTitle($title);
    }
    else {
      CRM_Utils_System::setTitle(ts('Search'));
    }
  }

  public function summary() {
    return NULL;
  }

}
?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script>
function CountryChange(value) {
  if(value!="") {
    var o;
    CRM.api3('StateProvince', 'get', {
      "sequential": 1,
      "return": ["name"],
      "country_id": value,
      "options": {"limit":0}
    }).then(function(result) {
      $("#s2id_kraj .select2-search-choice").remove();
      $("#kraj").find('option').remove();
      $("#kraj").attr('placeholder', '- libovolný kraj -');
      $("#kraj").attr("disabled", false);
      for (var i = 0; i < result["values"].length; i++) {
        o=new Option(result["values"][i]["name"], result["values"][i]["id"]);
        $(o).html(result["values"][i]["name"]);
        $("#kraj").append(o);

      }
    }, function(error) {
      // oops
    });
  } else {
    //$("#kraj option:selected").prop("selected", false);
    $("#s2id_kraj .select2-search-choice").remove();
    $("#kraj").find('option').remove();
    $("#kraj").attr("disabled", true);
  }

}
</script>
<?php
