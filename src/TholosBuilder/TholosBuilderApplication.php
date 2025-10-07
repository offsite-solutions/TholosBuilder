<?php
  /** @noinspection DuplicatedCode */
  /** @noinspection SpellCheckingInspection */
  /** @noinspection PhpUnusedFunctionInspection */
  /** @noinspection NotOptimalIfConditionsInspection */
  
  namespace TholosBuilder;
  
  use Eisodos\Abstracts\Singleton;
  use Eisodos\Eisodos;
  use Eisodos\Interfaces\DBConnectorInterface;
  use Eisodos\Parsers\CallbackFunctionParser;
  use Eisodos\Parsers\CallbackFunctionShortParser;
  use Eisodos\Parser\SQLParser;
  use Exception;
  use JetBrains\PhpStorm\NoReturn;
  use JsonException;
  use Redmine\Client;
  use RuntimeException;
  use Throwable;
  
  class TholosBuilderApplication extends Singleton {
    
    private array $DB_Objects;
    
    /**
     * @var string
     */
    private string $builder_schema;
    
    /**
     * @var string
     */
    private string $project_schema;
    
    private string $templateFolder = 'tholosbuilder/';
    /**
     * @var string
     */
    private string $project_owner;
    
    public TholosBuilderCallback $callback;
    
    private DBConnectorInterface $builder_db;
    private DBConnectorInterface $project_db;
    
    /**
     * @throws Exception
     */
    protected function init(array $options_): void {
      /* setting mandatory configs */
      Eisodos::$parameterHandler->setParam("TranslateLanguageTags", "F");
      
      $this->callback = new TholosBuilderCallback();
      
      Eisodos::$templateEngine->registerParser(new CallbackFunctionParser());
      Eisodos::$templateEngine->registerParser(new CallbackFunctionShortParser());
      Eisodos::$templateEngine->registerParser(new SQLParser());
      
      $this->builder_schema = Eisodos::$parameterHandler->getParam("TholosBuilder.BuilderSchema", "");
      $this->project_schema = Eisodos::$parameterHandler->getParam("TholosBuilder.ProjectSchema", "");
      $this->project_owner = Eisodos::$parameterHandler->getParam("TholosBuilder.ProjectOwner", "");
      
      $this->DB_Objects = [
        // ORACLE
        "oci8.sp.login" => "{BuilderSchema}app_logins_pkg.login",
        "oci8.sp.init" => "{BuilderSchema}app_session_pkg.init",
        "oci8.sp.component_copy" => "{BuilderSchema}app_components_pkg.copy",
        "oci8.sp.component_move" => "{BuilderSchema}app_components_pkg.move",
        "oci8.sp.component_move_up" => "{BuilderSchema}app_components_pkg.move_up",
        "oci8.sp.component_move_down" => "{BuilderSchema}app_components_pkg.move_down",
        "oci8.sp.component_move_first" => "{BuilderSchema}app_components_pkg.move_first",
        "oci8.sp.component_move_last" => "{BuilderSchema}app_components_pkg.move_last",
        "oci8.sp.component_insert" => "{BuilderSchema}app_components_pkg.insert_row",
        "oci8.sp.component_delete" => "{BuilderSchema}app_components_pkg.delete_row",
        "oci8.sp.component_clone" => "{BuilderSchema}app_components_pkg.clone",
        "oci8.sp.property_insert" => "{BuilderSchema}app_component_properties_pkg.insert_row",
        "oci8.sp.property_update" => "{BuilderSchema}app_component_properties_pkg.update_row",
        "oci8.sp.property_delete" => "{BuilderSchema}app_component_properties_pkg.delete_row",
        "oci8.sp.event_delete" => "{BuilderSchema}app_component_events_pkg.delete_row",
        "oci8.sp.event_insert" => "{BuilderSchema}app_component_events_pkg.insert_row",
        "oci8.sp.event_update" => "{BuilderSchema}app_component_events_pkg.update_row",
        "oci8.sp.wizard_query" => "{ProjectSchema}app_utl_pkg.get_query_columns",
        "oci8.sp.wizard_stored_procedure" => "{ProjectSchema}app_utl_pkg.get_procedure_arguments",
        "oci8.sp.help_insert" => "{BuilderSchema}app_help_pkg.insert_row",
        "oci8.sp.help_update" => "{BuilderSchema}app_help_pkg.update_row",
        "oci8.sp.help_delete" => "{BuilderSchema}app_help_pkg.delete_row",
        "oci8.sp.task_open" => "{BuilderSchema}app_tasks_pkg.open_task",
        "oci8.sp.task_close" => "{BuilderSchema}app_tasks_pkg.close_task",
        "oci8.sp.user_config" => "{BuilderSchema}app_users_pkg.set_user_config",
        // PostgreSQL
        "pgsql.sp.wizard_query" => "{ProjectSchema}get_query_columns",
        "pgsql.sp.wizard_stored_procedure" => "{ProjectSchema}get_procedure_arguments"
      ];
    }
    
    private function getDBObject(DBConnectorInterface $DBConnector_, string $object_id_): string {
      return str_replace(
        array('{BuilderSchema}', '{ProjectSchema}'),
        array($this->builder_schema, $this->project_schema),
        Eisodos::$utils->safe_array_value($this->DB_Objects, $DBConnector_->DBSyntax() . '.' . $object_id_, '')
      );
    }
    
    private function SPError(array $spResponse_): void {
      if ($spResponse_["p_error_code"] != "0") {
        Eisodos::$parameterHandler->setParam('SPError', 'T');
        throw new RuntimeException("[" . $spResponse_["p_error_code"] . "] " . $spResponse_["p_error_msg"]);
      }
    }
    
    private function safeHTML(string|null $text_): string {
      if ($text_===null || $text_=='') {
        return '';
      }
      return str_replace(array("[", "]", "\$", "^"), array("&#91;", "&#93;", "&#36;", "&#94;"), htmlspecialchars(stripslashes($text_)));
    }
    
    /**
     * @throws Throwable
     * @throws JsonException
     */
    #[NoReturn]
    public function run(
      DBConnectorInterface $builder_db_,
      DBConnectorInterface $project_db_
    ): void {
      
      $this->builder_db = $builder_db_;
      $this->project_db = $project_db_;
      
      // connect to db
      
      $this->builder_db->connect('Database1');
      $this->project_db->connect('Database2');
      
      // Authentication
      
      if (Eisodos::$parameterHandler->eq("action", "login")) {
        $this->login();
      } elseif (Eisodos::$parameterHandler->eq("action", "logout")) {
        $this->logout();
      } elseif (Eisodos::$parameterHandler->eq("tholosbuilder_login_id", "")) {
        $this->showLogin();
      }
      
      // session initialization
      
      $this->initSession();
      
      if (Eisodos::$parameterHandler->eq("action", "")) {
        Eisodos::$templateEngine->getTemplate($this->templateFolder . "main", array(), true);
        Eisodos::$render->finish();
        exit;
      }
      
      // simple routing
      
      $action = Eisodos::$parameterHandler->getParam('action');
      if (method_exists($this, $action)) {
        $this->$action();
      }
      
      exit;
      
    }
    
    /**
     * @throws JsonException
     */
    private function cloneComponent(): void {
      try {
        
        $ids = [];
        foreach (explode('|', Eisodos::$parameterHandler->getParam('p_ids')) as $x) {
          $xx = explode(',', $x);
          $ids[] = ['id' => $xx[0], 'version' => $xx[1]];
        }
        
        $boundVariables = [];
        $this->builder_db->bind($boundVariables, "p_json", "text", json_encode($ids, JSON_THROW_ON_ERROR));
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.component_clone")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function moveFirstComponent():void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        
        $this->builder_db->bindParam($boundVariables, "p_version", "integer");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.component_move_first")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function moveLastComponent():void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        
        $this->builder_db->bindParam($boundVariables, "p_version", "integer");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.component_move_last")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function moveDownComponent():void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        
        $this->builder_db->bindParam($boundVariables, "p_version", "integer");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.component_move_down")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function moveUpComponent():void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        
        $this->builder_db->bindParam($boundVariables, "p_version", "integer");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.component_move_up")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function moveMultiple():void {
      try {
        
        $this->builder_db->startTransaction();
        
        foreach (explode("|", Eisodos::$parameterHandler->getParam("p_nodes")) as $node) {
          
          // data.node.id,data.node.original.version,data.old_parent,data.parent,data.position
          $nodeparam = explode(",", $node);
          
          $boundVariables = [];
          $this->builder_db->bind($boundVariables, "p_component_id", "integer", $nodeparam[0]);
          $this->builder_db->bind($boundVariables, "p_old_parent_id", "integer", $nodeparam[2]);
          $this->builder_db->bind($boundVariables, "p_new_parent_id", "integer", $nodeparam[3]);
          $this->builder_db->bind($boundVariables, "p_new_position", "integer", $nodeparam[4]);
          
          $this->builder_db->bind($boundVariables, "p_version", "integer", $nodeparam[1]);
          $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
          $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
          $resultArray = array();
          
          $this->builder_db->executeStoredProcedure(
            ($this->getDBObject($this->builder_db, "sp.component_move")),
            $boundVariables,
            $resultArray
          );
          
          $this->SPError($resultArray);
          
        }
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function pasteComponent():void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        $this->builder_db->bindParam($boundVariables, "p_parent_id", "integer");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.component_copy")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function saveComponent():void {
      
      try {
        
        if (!preg_match('/^[A-Za-z_]{1}([A-Za-z0-9_])?/', Eisodos::$utils->replace_all(Eisodos::$parameterHandler->getParam("p_name"), '%ID%', '1'))) {
          throw new RuntimeException("Component name is not valid!");
        }
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        $this->builder_db->bindParam($boundVariables, "p_parent_id", "integer");
        $this->builder_db->bindParam($boundVariables, "p_component_type_id", "integer");
        
        $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
        $this->builder_db->bind($boundVariables, "p_version", "integer", "");
        $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.component_insert")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        /* adding name property */
        $name_property_id = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select property_id from app_component_properties_v cp where cp.l_name='name' && cp.component_id=" . $resultArray["p_id"]);
        
        $boundVariables = [];
        $this->builder_db->bind($boundVariables, "p_id", "integer", "");
        $this->builder_db->bind($boundVariables, "p_component_id", "integer", $resultArray["p_id"]);
        $this->builder_db->bind($boundVariables, "p_property_id", "integer", $name_property_id);
        $this->builder_db->bind($boundVariables, "p_value", "text",
          Eisodos::$parameterHandler->getParam("p_name", substr($this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select class_name from def_component_types ct where ct.id=" . $this->builder_db->nullStrParam("p_component_type_id", false)), 1) . $resultArray["p_id"]));
        $this->builder_db->bind($boundVariables, "p_value_component_id", "integer", "");
        
        $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
        $this->builder_db->bind($boundVariables, "p_version", "integer", "");
        $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray2 = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.property_insert")),
          $boundVariables,
          $resultArray2
        );
        
        $this->SPError($resultArray2);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
      
    }
    
    /**
     * @throws JsonException
     */
    private function addComponent():void {
      
      try {
        
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "component.add", array(), false);
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
      
    }
    
    /**
     * @throws JsonException
     */
    #[NoReturn]
    private function searchApp():void {
      
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "search.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
      
    }
    
    /**
     * @throws JsonException
     */
    private function loadAppTree():void {
      
      try {
        $apptree_ = array();
        $this->builder_db->query(RT_ALL_ROWS,
          "select t.id, \n" .
          "       t.name||'<span class=\"tree_class_name\">'||t.class_name||'</span>' as text, \n" .
          "       case when ''||t.parent_id is null then '#' else ''||t.parent_id end as parent, \n" .
          "       t.name as name, \n" .
          "       t.class_name as type, \n" .
          "       t.version \n" .
          "  from APP_TREE_PATH_V t \n" .
          " where parent_id is null \n " .
          " or route_id in (" . Eisodos::$parameterHandler->getParam("route_filter", "-1") . ") ",
          $apptree_);
        
        $responseArray['tree'] = json_encode($apptree_, JSON_THROW_ON_ERROR);
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        $responseArray['success'] = 'ERROR';
      }
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
      
    }
    
    /**
     * @throws JsonException
     */
    private function getNavFrame(): void {
      
      try {
        
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "navframe.main",
          array(), false);
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        $responseArray['success'] = 'ERROR';
      }
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
      
    }
    
    /**
     * @throws Throwable
     */
    private function login():void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, 'P_USER', 'string');
        $this->builder_db->bindParam($boundVariables, 'P_PASSWORD', 'string');
        
        $this->builder_db->bind($boundVariables, 'P_SESSION_ID', 'string', Eisodos::$parameterHandler->getParam("_sessionid", ""));
        $this->builder_db->bind($boundVariables, 'P_LOGIN_ID', 'int', '', 'OUT');
        $this->builder_db->bind($boundVariables, 'P_ERROR_MSG', 'string', '', 'OUT');
        $this->builder_db->bind($boundVariables, 'P_ERROR_CODE', 'int', '', 'OUT');
        
        $resultArray = [];
        $this->builder_db->executeStoredProcedure(
          $this->getDBObject($this->builder_db, 'sp.login'),
          $boundVariables,
          $resultArray,
          true);
        
        $this->SPError($resultArray);
        
        $this->builder_db->commit();
        
        Eisodos::$parameterHandler->setParam('tholosbuilder_login_id', $resultArray['p_login_id'], true);
        Eisodos::$parameterHandler->setParam('REDIRECT', Eisodos::$parameterHandler->getParam('TholosBuilderAppURL'));
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        Eisodos::$templateEngine->getTemplate(
          $this->templateFolder . 'login.main',
          array('ERRORMSG' => 'Login error (' . $e->getMessage() . ')'),
          true
        );
      }
      
      Eisodos::$render->finish();
      exit;
    }
    
    #[NoReturn]
    private function logout(): void {
      Eisodos::$render->logout();
      Eisodos::$parameterHandler->setParam("REDIRECT", Eisodos::$parameterHandler->getParam("TholosBuilderAppURL"));
      Eisodos::$render->finish();
      exit;
    }
    
    #[NoReturn]
    private function showLogin(): void {
      if (Eisodos::$parameterHandler->eq("IsAjaxRequest", "T") && Eisodos::$parameterHandler->neq("action", "showlogin")) {
        header("X-Tholos-Redirect: " . Eisodos::$parameterHandler->getParam("TholosBuilderAppURL") . "?action=showlogin");
        Eisodos::$render->finish();
        exit;
      }
      
      Eisodos::$templateEngine->getTemplate($this->templateFolder . "login.main", array(), true);
      Eisodos::$render->finish();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function initSession(): void {
      
      try {
        
        $boundVariables = [];
        
        $this->builder_db->bind($boundVariables, "p_login_id", "integer", Eisodos::$parameterHandler->getParam("tholosbuilder_login_id"));
        $this->builder_db->bind($boundVariables, "p_user_name", "text", "");
        $this->builder_db->bind($boundVariables, "p_task_number", "integer", "");
        $this->builder_db->bind($boundVariables, "p_task_subject", "text", "");
        $this->builder_db->bind($boundVariables, "p_task_project_id", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.init")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $this->builder_db->commit();
        
        Eisodos::$parameterHandler->setParam("task_number", $resultArray["p_task_number"]);
        Eisodos::$parameterHandler->setParam("task_subject", $resultArray["p_task_subject"]);
        Eisodos::$parameterHandler->setParam("task_project_id", $resultArray["p_task_project_id"]);
        Eisodos::$parameterHandler->setParam("user_name", $resultArray["p_user_name"]);
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        if (Eisodos::$parameterHandler->neq("IsAJAXRequest", "T")) {
          Eisodos::$render->logout();
          Eisodos::$parameterHandler->setParam("REDIRECT", Eisodos::$parameterHandler->getParam("TholosBuilderAppURL"));
        } else {
          $responseArray['success'] = 'ERROR';
          header('Content-type: application/json');
          Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
        }
        Eisodos::$render->finish();
        exit;
      }
      
    }
    
    /**
     * @throws JsonException
     */
    private function deleteComponent(): void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        
        $this->builder_db->bindParam($boundVariables, "p_version", "integer");
        $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.component_delete")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function deleteComponents(): void {
      try {
        
        $ids = Eisodos::$parameterHandler->getParam("p_ids", "");
        
        foreach (explode("|", $ids) as $item) {
          
          if ($this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select id from app_components a where a.ID=" . explode(",", $item)[0]) != "") {
            
            $boundVariables = [];
            $this->builder_db->bind($boundVariables, "p_id", "integer", explode(",", $item)[0]);
            
            $this->builder_db->bind($boundVariables, "p_version", "integer", explode(",", $item)[1]);
            $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
            $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
            $resultArray = array();
            
            $this->builder_db->startTransaction();
            $this->builder_db->executeStoredProcedure(
              ($this->getDBObject($this->builder_db, "sp.component_delete")),
              $boundVariables,
              $resultArray
            );
            
            $this->SPError($resultArray);
            
          }
          
        }
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function showPropertiesAndEventsHead(): void {
      try {
        
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.container", array(), false);
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        $responseArray['success'] = 'ERROR';
      }
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    private function showPropertiesAndEvents(): void {
      try {
        
        if (Eisodos::$parameterHandler->eq("p_property_id", "") && Eisodos::$parameterHandler->eq("p_event_id", "")) {
          $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.tab." . Eisodos::$parameterHandler->getParam("p_tab_index", ""), array(), false);
        }
        elseif (Eisodos::$parameterHandler->neq("p_property_id", "")) {
          $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.property.sql", array(), false);
        }
        else {
          $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.event.sql", array(), false);
        }
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        $responseArray['success'] = 'ERROR';
      }
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    private function editProperty(): void {
      try {
        $options = "";
        $p_value = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select value from app_component_properties cp where cp.id=" . $this->builder_db->nullStrParam("p_link_id", false));
        $p_value2 = $p_value;
        
        $back = array();
        $this->builder_db->query(RT_FIRST_ROW, "select type,value_list from def_properties where id=" . $this->builder_db->nullStrParam("p_property_id", false), $back);
        if ($back["value_list"] != '') {
          Eisodos::$parameterHandler->setParam("p_type", "LIST");
          foreach (explode("\n", $back["value_list"]) as $row) {
            $found = (trim($row) == $p_value);
            $options .= '<option value="' . trim($row) . '" ' . ($found ? 'selected' : '') . '>' . trim($row) . '</option>';
            if ($found) {
              $p_value2 = "";
            }
          }
        } elseif ($back["type"] == 'BOOLEAN') {
          if ($p_value == "true" || $p_value == "false") {
            $p_value2 = "";
          }
        } elseif ($back["type"] == 'TEMPLATE') {
          $p_value2 = $p_value;
          Eisodos::$parameterHandler->setParam("p_type", "LIST");
          $dir = opendir(Eisodos::$parameterHandler->getParam("TemplateDir"));
          $filenames = [];
          while ($file = readdir($dir)) {
            if (preg_match("/\.template$/", $file)) {
              $filename = Eisodos::$utils->replace_all($file, ".template", "", true, true);
              $filenames[] = $filename;
            }
          }
          closedir($dir);
          sort($filenames);
          foreach ($filenames as $filename) {
            $found = ($filename == $p_value);
            $options .= '<option value="' . $filename . '" ' . ($found ? 'selected' : '') . '>' . $filename . '</option>';
            if ($found) {
              $p_value2 = "";
            }
          }
        }
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.form." . Eisodos::$parameterHandler->getParam("p_type"),
          array("p_value" => $this->safeHTML($p_value),
            "p_value2" => $this->safeHTML($p_value2),
            "options" => $options
          ),
          false);
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    private function saveProperty(): void {
      try {
        
        // check is it the name property?
        $isname = false;
        $isname = ($this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select l_name \n" .
            "  from app_component_properties_v cpv \n" .
            " where cpv.component_id=" . $this->builder_db->nullStrParam("p_component_id", false) .
            "       and cpv.property_id=" . $this->builder_db->nullStrParam("p_property_id", false)) == "name");
        
        if (Eisodos::$parameterHandler->eq("p_value", "") && Eisodos::$parameterHandler->eq("p_value_component_id", "")) {
          if (Eisodos::$parameterHandler->neq("p_id", "")) {
            
            if ($isname) throw new RuntimeException("Name property can not be null!");
            
            $boundVariables = [];
            $this->builder_db->bindParam($boundVariables, "p_id", "integer");
            
            $this->builder_db->bindParam($boundVariables, "p_version", "integer");
            $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
            $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
            $resultArray = array();
            
            $this->builder_db->startTransaction();
            $this->builder_db->executeStoredProcedure(
              ($this->getDBObject($this->builder_db, "sp.property_delete")),
              $boundVariables,
              $resultArray
            );
            
            $this->SPError($resultArray);
            
          }
        } else {
          if ($isname) {
            if (!preg_match('/^[A-Za-z_]{1}([A-Za-z0-9_])?/', Eisodos::$parameterHandler->getParam("p_value")))
              throw new RuntimeException("Value of name property is not valid!");
          }
          
          if (Eisodos::$parameterHandler->neq("p_value", "") && Eisodos::$parameterHandler->neq("p_value_component_id", ""))
            throw new RuntimeException("Selector and component could not be selected at the same time!");
          
          $boundVariables = [];
          $this->builder_db->bindParam($boundVariables, "p_id", "integer");
          $this->builder_db->bindParam($boundVariables, "p_component_id", "integer");
          $this->builder_db->bindParam($boundVariables, "p_property_id", "integer");
          $this->builder_db->bindParam($boundVariables, "p_value", "text");
          $this->builder_db->bindParam($boundVariables, "p_value_component_id", "integer");
          
          $this->builder_db->bindParam($boundVariables, "p_version", "integer");
          $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
          if (Eisodos::$parameterHandler->eq("p_id", "")) $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
          $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
          $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
          $resultArray = array();
          
          $this->builder_db->startTransaction();
          $this->builder_db->executeStoredProcedure(
            ($this->getDBObject($this->builder_db, "sp.property_" . (Eisodos::$parameterHandler->eq("p_id", "") ? "insert" : "update"))),
            $boundVariables,
            $resultArray
          );
          
          $this->SPError($resultArray);
        }
        
        $responseArray['success'] = 'OK';
        $responseArray['refreshtree'] = ($isname ? "Y" : "N");
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showComponentTypeDocumentation(): void {
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "docs.ctypes.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function editEvent(): void {
      try {
        
        $methodlist = "";
        $methods = "";
        $value = array();
        $this->builder_db->query(RT_FIRST_ROW, "select value,value_component_id,value_method_id,parameters \n" .
          "  from app_component_events cp \n" .
          " where cp.id=" . $this->builder_db->nullStrParam("p_link_id", false), $value);
        
        $back = array();
        $components = '<option value=""></option>';
        $sql = "select * from ( \n" .
          "select distinct c.id, substr(c.path,instr(c.path,'.')+1)||':'||c.class_name as path, 0, c.component_order \n" .
          "  from app_tree_path_v c \n" .
          "       join def_component_type_method_v ctm on ctm.root_component_id=c.component_type_id \n" .
//              " where c.route = (select route from app_tree_path_v c2 where c2.id=".Eisodos::$parameterHandler->getParam("p_component_id","-1").") \n".
          "  where c.route_id in (" . Eisodos::$parameterHandler->getParam("route_filter", "-1") . ") \n" .
          "union \n" .
          "  select null as id, '------------------------------------------' as path, 1, null from dual \n" .
          "union \n" .
          "select distinct c.id, substr(c.path,instr(c.path,'.')+1)||':'||c.class_name as path, 2, c.component_order \n" .
          "  from app_tree_path_v c \n" .
          "       join def_component_type_method_v ctm on ctm.root_component_id=c.component_type_id \n" .
//              " where c.route != (select route from app_tree_path_v c2 where c2.id=".Eisodos::$parameterHandler->getParam("p_component_id","-1").") \n".
          " where c.id=" . Eisodos::$utils->safe_array_value($value, "value_component_id", "-1") . "  \n" .
          ") order by 3,4";
        
        
        $this->builder_db->query(RT_ALL_ROWS, $sql, $back);
        foreach ($back as $row) {
          $components .= '<option value="' . $row["id"] . '" ' . ($row["id"] != '' && $row["id"] == $value["value_component_id"] ? "selected" : "") . '>' . $row["path"] . '</option>';
        }
        
        if ($value["value_method_id"] != "") {
          
          $sql = "select ctm.id, ctm.name||'()' as method\n" .
            "  from app_tree_path_v c \n" .
            "  join def_component_type_method_v ctm on ctm.root_component_id=c.component_type_id \n" .
            "  where c.id=" . $value["value_component_id"] . " \n" .
            "  order by 2";
          $back = array();
          $this->builder_db->query(RT_ALL_ROWS, $sql, $back);
          foreach ($back as $row) {
            $methods .= '<option value="' . $row["id"] . '" ' . ($row["id"] == $value["value_method_id"] ? "selected" : "") . '>' . $row["method"] . '</option>';
          }
          $methodlist = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.event.methods", array("methods" => $methods), false);
        }
        
        
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.event.form",
          array("p_value" => $this->safeHTML($value["value"]),
            "p_parameters" => $value["parameters"],
            "components" => $components,
            "methodlist" => $methodlist
          ),
          false);
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    private function loadEventComponents(): void {
      try {
        
        $where = "";
        foreach (explode(" ", Eisodos::$parameterHandler->getParam("p_search")) as $val) {
          $where .= " and lower(path) like lower('%" . $val . "%')";
        }
        
        $sql = "select * from ( \n" .
          "select distinct c.id, substr(c.path,instr(c.path,'.')+1)||':'||c.class_name as path, 0, c.component_order \n" .
          "  from app_tree_path_v c \n" .
          "       join def_component_type_method_v ctm on ctm.root_component_id=c.component_type_id \n" .
          " where c.route = (select route from app_tree_path_v c2 where c2.id=" . Eisodos::$parameterHandler->getParam("p_component_id", "-1") . ") \n" .
          "union \n" .
          "  select null as id, '------------------------------------------' as path, 1, null from dual \n" .
          "union \n" .
          "select distinct c.id, substr(c.path,instr(c.path,'.')+1)||':'||c.class_name as path, 2, c.component_order \n" .
          "  from app_tree_path_v c \n" .
          "       join def_component_type_method_v ctm on ctm.root_component_id=c.component_type_id \n" .
          " where c.route != (select route from app_tree_path_v c2 where c2.id=" . Eisodos::$parameterHandler->getParam("p_component_id", "-1") . ") \n" .
          ") as t \n" .
          " where 1=1 \n" .
          $where .
          " order by 3,4";
        $back = array();
        $this->builder_db->query(RT_ALL_ROWS, $sql, $back);
        foreach ($back as $row) {
          $v = $row["path"];
          $v = Eisodos::$utils->replace_all($v, ".", "/", false, false);
          $v = Eisodos::$utils->replace_all($v, ".", "<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", false, false);
          foreach (explode(" ", Eisodos::$parameterHandler->getParam("p_search")) as $val) {
            $v = preg_replace('/(' . $val . ')/i', '<b>$1</b>', $v);
          }
          $components .= '<a href="javascript:$(\'#event_component_' . Eisodos::$parameterHandler->getParam('p_event_id') . ' option[value=' . $row["id"] . ']\').prop(\'selected\', true);loadMethods(' . Eisodos::$parameterHandler->getParam('p_event_id') . ',' . $row["id"] . ');">' . $v . '</a><br/>';
        }
        $responseArray['html'] = $components;
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    private function loadMethods(): void {
      try {
        $methodlist = "";
        $methods = "";
        
        if (Eisodos::$parameterHandler->neq("p_component_id", "")) {
          
          $sql = "select ctm.id, ctm.name||'()' as method\n" .
            "  from app_tree_path_v c \n" .
            "  join def_component_type_method_v ctm on ctm.root_component_id=c.component_type_id \n" .
            "  where c.id=" . $this->builder_db->nullStrParam("p_component_id", false) . " \n" .
            "  order by 2";
          $back = array();
          $this->builder_db->query(RT_ALL_ROWS, $sql, $back);
          foreach ($back as $row) {
            $methods .= '<option value="' . $row["id"] . '" ' . ($row["id"] == Eisodos::$parameterHandler->getParam("value_method_id") ? "selected" : "") . '>' . $row["method"] . '</option>';
          }
          $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.event.methods", array("methods" => $methods), false);
        } else $responseArray['html'] = "";
        
        $responseArray['success'] = 'OK';
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    private function saveEvent():void {
      try {
        
        if (Eisodos::$parameterHandler->eq("p_value", "") && Eisodos::$parameterHandler->eq("p_value_component_id", "")) {
          if (Eisodos::$parameterHandler->neq("p_id", "")) {
            
            $boundVariables = [];
            $this->builder_db->bindParam($boundVariables, "p_id", "integer");
            
            $this->builder_db->bindParam($boundVariables, "p_version", "integer");
            $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
            $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
            $resultArray = array();
            
            $this->builder_db->startTransaction();
            $this->builder_db->executeStoredProcedure(
              ($this->getDBObject($this->builder_db, "sp.event_delete")),
              $boundVariables,
              $resultArray
            );
            
            $this->SPError($resultArray);
            
          }
        } else {
          
          if (Eisodos::$parameterHandler->neq("p_value_component_id", "") && Eisodos::$parameterHandler->eq("p_value_method_id", ""))
            throw new RuntimeException("Method is not selected!");
          
          if (Eisodos::$parameterHandler->neq("p_value_component_id", "")) Eisodos::$parameterHandler->setParam("p_value", "");
          
          $boundVariables = [];
          $this->builder_db->bindParam($boundVariables, "p_id", "integer");
          $this->builder_db->bindParam($boundVariables, "p_component_id", "integer");
          $this->builder_db->bindParam($boundVariables, "p_event_id", "integer");
          $this->builder_db->bindParam($boundVariables, "p_value", "text");
          $this->builder_db->bindParam($boundVariables, "p_value_component_id", "integer");
          $this->builder_db->bindParam($boundVariables, "p_value_method_id", "integer");
          $this->builder_db->bindParam($boundVariables, "p_parameters", "text");
          
          $this->builder_db->bindParam($boundVariables, "p_version", "integer");
          $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
          if (Eisodos::$parameterHandler->eq("p_id", "")) $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
          $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
          $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
          $resultArray = array();
          
          $this->builder_db->startTransaction();
          $this->builder_db->executeStoredProcedure(
            ($this->getDBObject($this->builder_db, "sp.event_" . (Eisodos::$parameterHandler->eq("p_id", "") ? "insert" : "update"))),
            $boundVariables,
            $resultArray
          );
          
          $this->SPError($resultArray);
        }
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function compile2(): void {
      
      try {
        
        set_time_limit(60 * 60);
        
        $start = microtime(true);
        $step = 0;
        
        $this->builder_db->startTransaction();
        /* deleting deferred properties */
        $sql = "delete \n" .
          "  from app_component_properties cp \n" .
          " where cp.property_id not in (select ctp.property_id from def_component_type_properties ctp)";
        $this->builder_db->executeDML($sql);
        
        // Eisodos::$logger->debug("Step  1: ".(microtime(true) - $start));
        
        /* deleting deferred events */
        $sql = "delete \n" .
          "  from app_component_events ce \n" .
          " where ce.event_id not in (select cte.event_id from def_component_type_events cte)";
        $this->builder_db->executeDML($sql);
        
        $this->builder_db->commit();
        
        // Eisodos::$logger->debug("Step  2: ".(microtime(true) - $start));
        
        $sql = "select ct.class_name, ct2.class_name as ancestor_name \n" .
          "  from def_component_types ct \n" .
          "  join def_component_types ct2 on ct2.id=ct.ancestor_id \n" .
          "  order by 1";
        $componentTypes = array();
        $this->builder_db->query(RT_ALL_KEY_VALUE_PAIRS, $sql, $componentTypes);
        
        // Eisodos::$logger->debug("Step  3: ".(microtime(true) - $start));
        
        /* component type index */
        $sql = "select ctm.id as n, \n" .
          "       (select max(substr(sys_connect_by_path(ct3.class_name,'.'),2)) \n" .
          "          from def_component_types ct3 \n" .
          "       connect by prior ct3.ancestor_id=ct3.id \n" .
          "       start with ct3.id=ctm.id) as h \n" .
          "  from def_component_types ctm \n" .
          "  order by id";
        $componentTypeIndex = array();
        $this->builder_db->query(RT_ALL_ROWS_ASSOC, $sql, $componentTypeIndex, ['indexFieldName' => 'n']);
        
        // Eisodos::$logger->debug("Step  4: ".(microtime(true) - $start));
        
        /* component id route index */
        
        $sql = "select c.id, \n" .
          "       c.parent_id as pid, \n" .
          "       ct.id as p, \n" .
          "       acp.value as n, \n" .
          "       tpv.route as c, \n" .
          "       tpv.route_id as r, \n" .
          "       tpv.action_id as a \n" .
          "  from app_components c, \n" .
          "       def_component_types ct, \n" .
          "       app_component_properties_v acp, \n" .
          "       app_tree_path_v tpv \n" .
          " where ct.id=c.component_type_id \n" .
          "       and acp.l_name='name' \n" .
          "       and acp.component_id=c.id \n" .
          "       and tpv.id=c.id \n" .
          " order by c.parent_id nulls first, c.creation_order";
        
        $componentIndex = array();
        $this->builder_db->query(RT_ALL_ROWS_ASSOC, $sql, $componentIndex, ['indexFieldName' => 'id']);
        
        // Eisodos::$logger->debug("Step  5: ".(microtime(true) - $start));
        
        $applicationCachePHP = "<?php \n" .
          "  \$this->componentTypes=" . var_export($componentTypes, true) . "; \n" .
          "  \$this->componentTypeIndex=" . var_export($componentTypeIndex, true) . "; \n" .
          "  \$this->componentIndex=" . var_export($componentIndex, true) . "; \n" .
          "?>";
        
        if (Eisodos::$parameterHandler->eq("action", "remoteCompile2")) {
          $responseArray['CompiledApplication'] = base64_encode($applicationCachePHP);
        }
        
        if (Eisodos::$parameterHandler->neq("action", "remoteCompile2") || Eisodos::$parameterHandler->eq("localCompile", "T")) {
          
          if (file_exists(Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationCacheDir") . "_compilation.status") && Eisodos::$parameterHandler->neq("compileall", "T")) {
            $last_compilation_time = file_get_contents(Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationCacheDir") . "_compilation.status");
          } else $last_compilation_time = "2000-01-01 00:00:00";
          
          Eisodos::$parameterHandler->setParam("LAST_COMPILATION_TIME", $last_compilation_time);
          
          $statusfile = fopen(Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationCacheDir") . "_compilation.status", 'wb');
          fwrite($statusfile, $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "SELECT TO_CHAR(SYSDATE,'YYYY-MM-DD HH24:MI:SS') FROM DUAL"));
          fclose($statusfile);
          
          ($applicationFile = fopen(Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationCacheDir") . "_tholos.init", 'wb'))
          || die("can't open Tholos Application Cache file (" . Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationCacheDir") . "_tholos.init" . ")");
          fwrite($applicationFile, $applicationCachePHP);
          fclose($applicationFile);
        }
        
        // free up
        unset($applicationCachePHP);
        
        // Eisodos::$logger->debug("Step  6: ".(microtime(true) - $start));
        
        /* TRoutes */
        $sql = " select distinct route from (\n" .
          " select route \n" .
          "  from app_routes_v a \n" .
          " where to_char(a.last_update_date,'YYYY-MM-DD HH24:MI:SS')>='" . Eisodos::$parameterHandler->getParam("LAST_COMPILATION_TIME", "2000-01-01 00:00:00") . "' \n" .
          " union \n" .
          " select atp.route \n" .
          "   from app_tasks at \n" .
          "   join app_users au on au.id=at.created_by and au.id=app_session_pkg.user_id \n" .
          "   left outer join app_changes ac on at.id=ac.task_id \n" .
          "   left outer join app_tree_path_v atp on ac.component_id=atp.id \n" .
          "  where at.committed is null \n" .
          "        and at.closed='N'  \n" .
          "  ) \n" .
          " where route is not null \n" .
          " order by 1";
        
        $routes = array();
        $this->builder_db->query(RT_ALL_FIRST_COLUMN_VALUES, $sql, $routes);
        
        // Eisodos::$logger->debug("Step  8: ".(microtime(true) - $start));
        
        /* all events */
        $sql = "SELECT ce.component_id    AS id, \n" .
          "       l_name             AS n, \n" .
          "       TYPE               AS t, \n" .
          "       VALUE              AS v, \n" .
          "       method_name        AS m, \n" .
          "       method_path        AS p, \n" .
          "       value_component_id AS c, \n" .
          "       value_method_id    AS i, \n" .
          "       ce.parameters      AS a \n" .
          "  FROM app_component_events_v ce \n" .
          " WHERE (VALUE IS NOT NULL OR value_method_id IS NOT NULL) \n" .
          " ORDER BY ce.component_id";
        $events = array();
        $this->builder_db->query(RT_ALL_ROWS, $sql, $events);
        
        $eventids = array();
        $eventids = array_column($events, "id");
        
        // Eisodos::$logger->debug("Step  9: ".(microtime(true) - $start));
        
        foreach ($routes as $route) {
          
          /*          $sql = "select c.id, \n".
                   "       parent_id as pid, \n".
                   "       c.component_type_id as t, \n".
                   "       acp.value as o \n".
                   "  from app_components c, \n".
                   "       def_component_types ct, \n".
                   "       app_component_properties_v acp \n".
                   " where ct.id=c.component_type_id \n".
                   "       and acp.l_name='name' \n".
                   "       and acp.component_id=c.id \n".
                   "       and c.id in (select tv.id \n".
                   "                      from app_tree_path_v tv \n".
                   "                     where coalesce(tv.route,'application')='".$route."') \n".
                   " order by c.parent_id nulls first, c.creation_order"; */
          
          /* 2017.12.11. - included help_id as h */
          
          $sql = "select c.id, \n" .
            "       parent_id as pid, \n" .
            "       c.component_type_id as t, \n" .
            "       acp.value as o, \n" .
            "       coalesce(ah.help_id,ah.id) as h \n" .
            "  from app_components c \n" .
            "  join def_component_types ct on ct.id=c.component_type_id \n" .
            "  join app_component_properties_v acp on acp.l_name='name' and acp.component_id=c.id \n" .
            "  left outer join app_help ah on ah.component_id=c.id \n" .
            " where c.id in (select tv.id \n" .
            "                  from app_tree_path_v tv \n" .
            "                 where coalesce(tv.route,'application')='" . $route . "') \n" .
            " order by c.parent_id nulls first, c.creation_order \n";
          
          $back4 = array();
          $this->builder_db->query(RT_ALL_ROWS_ASSOC, $sql, $back4, ['indexFieldName' => 'id']);
          
          // Eisodos::$logger->debug("Step 10-".($step++).": ".(microtime(true) - $start));
          
          /* all properties */
          $sql = "SELECT cp.component_id    AS id, \n" .
            "       l_name             AS n, \n" .
            "       TYPE               AS t, \n" .
            "       VALUE              AS v, \n" .
            "       value_component_id AS c, \n" .
            "       nodata             AS d \n" .
            "  FROM app_component_properties_v cp \n" .
            " WHERE cp.component_id in (select tv.id \n" .
            "                             from app_tree_path_v tv \n" .
            "                            where coalesce(tv.route,'application')='" . $route . "') \n" .
            " ORDER BY cp.component_id, 2";
          $properties = array();
          $this->builder_db->query(RT_ALL_ROWS, $sql, $properties);
          
          // setting helpindex to the corresponding component
          foreach ($back4 as $id => $row) {
            if ($row["h"]) $properties[] = array("id" => $id, "n" => "helpindex", "t" => "NUMBER", "v" => $row["h"], "c" => NULL, "d" => "N");
          }
          
          $propids = array_column($properties, "id");
          
          /* getting properties */
          foreach ($back4 as $id => $row) {
            $a = array();
            foreach (array_keys($propids, $id) as $prrowkey) {
              $prrow = $properties[$prrowkey];
              unset($prrow["id"]);
              $a[] = $prrow;
            }
            $back4[$id]["p"] = json_encode($a);
          }
          
          // Eisodos::$logger->debug("Step 11-".($step++).": ".(microtime(true) - $start));
          
          /* getting events */
          foreach ($back4 as $id => $row) {
            $a = array();
            foreach (array_keys($eventids, $id) as $prrowkey) {
              $prrow = $events[$prrowkey];
              unset($prrow["id"]);
              $a[] = $prrow;
            }
            $back4[$id]["e"] = json_encode($a);
          }
          
          // Eisodos::$logger->debug("Step 12-".($step++).": ".(microtime(true) - $start));
          
          $routeCachePHP = "<?php \n" .
            "  \$this->componentDefinitions=\$this->componentDefinitions + " . var_export($back4, true) . "; \n" .
            "?>";
          
          // generating source code for SVN
          if (Eisodos::$parameterHandler->neq("Tholos.ApplicationSourceWorkingDir", "")) {
            $sourcecodesql =
              "SELECT CASE \n" .
              "        WHEN lag(q.path, \n" .
              "                  1, \n" .
              "                  NULL) over(ORDER BY q.path) = q.path THEN \n" .
              "          NULL \n" .
              "         ELSE \n" .
              "          q.path || ':'||q.class_name||' = {' || chr(13) || chr(10) \n" .
              "       END || CASE \n" .
              "         WHEN ord = 0 THEN \n" .
              "          '  ' || q.name || ' = ' || CASE \n" .
              "            WHEN q.value_component_id IS NOT NULL THEN \n" .
              "             '-> ' || q.path2 \n" .
              "            ELSE \n" .
              "             q.value \n" .
              "          END \n" .
              "         ELSE \n" .
              "          '  ' || q.name || ' = ' || CASE \n" .
              "            WHEN q.value_component_id IS NOT NULL THEN \n" .
              "             '-> ' || q.path2 || '.' || q.method_name \n" .
              "            ELSE \n" .
              "             q.value \n" .
              "          END || CASE \n" .
              "            WHEN q.parameters IS NOT NULL THEN \n" .
              "             '(' || q.parameters || ')' \n" .
              "            ELSE \n" .
              "             '' \n" .
              "          END \n" .
              "       END || CASE \n" .
              "         WHEN lead(q.path, \n" .
              "                   1, \n" .
              "                   NULL) over(ORDER BY q.path) != q.path THEN \n" .
              "          chr(13) || chr(10) || '}' || chr(13) || chr(10) \n" .
              "         ELSE \n" .
              "          NULL \n" .
              "       END AS text \n" .
              "  FROM (SELECT tp.route, \n" .
              "               tp.path, \n" .
              "               0                     AS ord, \n" .
              "               cp.name, \n" .
              "               cp.value, \n" .
              "               cp.value_component_id, \n" .
              "               tp2.path              AS path2, \n" .
              "               NULL                  AS method_name, \n" .
              "               NULL                  AS PARAMETERS, \n" .
              "               tp.component_order, \n" .
              "               tp.class_name \n" .
              "          FROM app_tree_path_v tp \n" .
              "          JOIN app_component_properties_v cp ON cp.component_id = tp.id \n" .
              "          LEFT OUTER JOIN app_tree_path_v tp2 ON tp2.id = cp.value_component_id \n" .
              "         WHERE (cp.value IS NOT NULL OR tp2.id IS NOT NULL) \n" .
              "           AND coalesce(tp.route,'application')='" . $route . "' \n" .
              "        UNION \n" .
              "        SELECT tp.route, \n" .
              "               tp.path, \n" .
              "               1                     AS ord, \n" .
              "               cp.name, \n" .
              "               cp.value, \n" .
              "               cp.value_component_id, \n" .
              "               tp2.path              AS path2, \n" .
              "               cp.method_name, \n" .
              "               cp.parameters, \n" .
              "               tp.component_order, \n" .
              "               tp.class_name \n" .
              "          FROM app_tree_path_v tp \n" .
              "          JOIN app_component_events_v cp ON cp.component_id = tp.id \n" .
              "          LEFT OUTER JOIN app_tree_path_v tp2 ON tp2.id = cp.value_component_id \n" .
              "         WHERE (cp.value IS NOT NULL OR tp2.id IS NOT NULL) \n" .
              "           AND coalesce(tp.route,'application')='" . $route . "') q \n" .
              " ORDER BY q.path, \n" .
              "          q.ord, \n" .
              "          q.component_order, \n" .
              "          q.name \n";
            
            $sourcecode_ = array();
            $this->builder_db->query(RT_ALL_FIRST_COLUMN_VALUES, $sourcecodesql, $sourcecode_);
            $routeSourceCode = implode("\n", $sourcecode_);
          }
          
          if (Eisodos::$parameterHandler->eq("action", "remoteCompile2")) {
            $responseArray['routeCachePHP'][$route] = base64_encode($routeCachePHP);
            if (Eisodos::$parameterHandler->neq("Tholos.ApplicationSourceWorkingDir", "")) $responseArray['routeSourceCode'][$route] = base64_encode($routeSourceCode);
          }
          
          if (Eisodos::$parameterHandler->neq("action", "remoteCompile2") || Eisodos::$parameterHandler->eq("localCompile", "T")) {
            $routeFile = fopen(Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationCacheDir") . $route . ".tcd", 'wb');
            fwrite($routeFile, $routeCachePHP);
            fclose($routeFile);
            if (Eisodos::$parameterHandler->neq("Tholos.ApplicationSourceWorkingDir", "")) {
              if (!mkdir($sourceWorkingDir = Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceWorkingDir") . Eisodos::$parameterHandler->getParam("user_name")) && !is_dir($sourceWorkingDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $sourceWorkingDir));
              }
              $routeSourceFile = fopen(Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceWorkingDir") . Eisodos::$parameterHandler->getParam("user_name") . "/" . $route . ".tcs", 'wb');
              fwrite($routeSourceFile, $routeSourceCode);
              fclose($routeSourceFile);
            }
          }
          
        }
        
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "compile.result",
          array("TholosApplicationCache" => Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationCache"),
            "CompilationTime" => (microtime(true) - $start),
            "Compiled" => implode(",", $routes)
          ),
          false);
        
        $responseArray['success'] = "OK";
        
        // update application's last compile time
        
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    /* wizard */
    private function showQueryWizard(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.query.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function QueryWizardRun(): void {
      try {
        
        $o_columns = array();
        
        $component_SQL = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select cpv.value from app_component_properties_v cpv where cpv.component_id=" . $this->builder_db->nullStrParam("p_component_id", false) . " and cpv.name='SQL'");
        
        if (Eisodos::$parameterHandler->eq("p_trans_root", ""))
          $transroot = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select route from APP_TREE_PATH_V t where t.ID=" . $this->builder_db->nullStrParam("p_component_id", false));
        else $transroot = Eisodos::$parameterHandler->getParam("p_trans_root");
        
        $component_SQL = Eisodos::$utils->replace_all($component_SQL, ":filter", " and 0=1");
        $component_SQL = Eisodos::$utils->replace_all($component_SQL, ":orderby", " 1");
        $component_SQL = Eisodos::$utils->replace_all($component_SQL, ":columns", "");
        
        $boundVariables = [];
        if ($this->project_owner != "") {
          $this->project_db->bind($boundVariables, "p_owner", "text", $this->project_owner);
        }
        
        $this->project_db->bind($boundVariables, "p_trans_root", "text", mb_strtoupper($transroot));
        $this->project_db->bind($boundVariables, "p_query", "text", $component_SQL);
        $this->project_db->bind($boundVariables, "p_columns", "text", "");
        $this->project_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->project_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->project_db->startTransaction();
        $this->project_db->executeStoredProcedure(
          ($this->getDBObject($this->project_db, "sp.wizard_query")),
          $boundVariables,
          $resultArray,
          true,
          CASE_LOWER
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        $responseArray['html'] = "";
        $columns = array();
        
        try {
          $columns = json_decode($resultArray["p_columns"], true);
          //$responseArray['html'].="<h4>New properties</h4><pre>".print_r($columns,true)."</pre>";
        } catch (Exception $e) {
          $responseArray['html'] = "<h3>Error converting to JSON!</h3>" . $e->getMessage() . "<pre>" . $resultArray["p_columns"] . "</pre>";
        }
        
        try {
          
          $props = array();
          $props = ["FieldName", "Name", "Index", "NativeDataType", "Size", "DataType", "Label"];
          
          $sql = "select tp.id, \n";
          
          foreach ($props as $prop) {
            $sql .= "cpv_" . $prop . ".value       as o_" . $prop . ", \n" .
              "cpv_" . $prop . ".link_id     as o_" . $prop . "_LinkId, \n" .
              "cpv_" . $prop . ".version     as o_" . $prop . "_Version, \n" .
              "cpv_" . $prop . ".property_id as o_" . $prop . "_PropertyId, \n" .
              "''                        as o_" . $prop . "_status, \n";
          }
          
          $sql .= "'delete' as status \n" .
            "  from app_tree_path_v tp \n";
          
          foreach ($props as $prop) {
            $sql .= "left outer join app_component_properties_v cpv_" . $prop . " on cpv_" . $prop . ".component_id=tp.id and cpv_" . $prop . ".name='" . $prop . "' \n";
          }
          
          $sql .= " where tp.parent_id=" . $this->builder_db->nullStrParam("p_component_id", false) . " \n" .
            "       and tp.class_name='TDBField'";
          
          $this->builder_db->query(RT_ALL_ROWS, $sql, $o_columns);
          
        } catch (Exception $e) {
          $responseArray['html'] = "<h3>Error getting current properties!</h3>" . $e->getMessage();
        }
        
        // generating differences
        foreach ($columns as $column) {
          $found = false;
          foreach ($o_columns as &$o) {
            if (strtoupper($o["o_" . strtolower($props[0])]) == strtoupper($column[$props[0]])) {
              $found = true;
              $o["status"] = "uptodate";
              foreach ($props as $prop) {
                if ($o["o_" . strtolower($prop)] != $column[$prop]) {
                  // do not modify date type field
                  $skip = false;
                  if ($prop == 'DataType') {
                    if ((in_array($o["o_" . strtolower($prop)], array("date", "datetime", "datetimehm", "timestamp", "time")) && $column[$prop] == "date") ||
                      ($o["o_" . strtolower($prop)] == "float" && $column[$prop] == "integer") ||
                      (in_array($o["o_" . strtolower($prop)], array("bool", "text")) && $column[$prop] == "string")) $skip = true;
                  }
                  if ($prop == 'Label' && Eisodos::$parameterHandler->eq('p_skip_label', "Y")) $skip = true;
                  if ($prop == 'Name' && $o["o_" . strtolower($prop)] != $o["o_" . 'fieldname']) $skip = true;
                  if (!$skip) {
                    $o["o_" . strtolower($prop) . "_origvalue"] = $o["o_" . strtolower($prop)];
                    $o["o_" . strtolower($prop)] = $column[$prop];
                    $o["o_" . strtolower($prop) . "_status"] = "modify";
                    $o["status"] = "modify";
                  } else {
                    $o["o_" . strtolower($prop) . "_origvalue"] = $o["o_" . strtolower($prop)] . ' (' . $column[$prop] . ')';
                    $o["o_" . strtolower($prop) . "_status"] = "skip";
                  }
                }
              }
            }
          }
          unset($o);
          if (!$found) {
            $x = array("id" => "", "status" => "new");
            foreach ($props as $prop) {
              $x["o_" . strtolower($prop)] = $column[$prop];
              $x["o_" . strtolower($prop) . "_linkid"] = "";
              $x["o_" . strtolower($prop) . "_origvalue"] = "";
              $x["o_" . strtolower($prop) . "_propertyid"] = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select id from DEF_PROPERTIES p where lower(p.name)='" . strtolower($prop) . "'");
              $x["o_" . strtolower($prop) . "_status"] = "new";
            }
            $o_columns[] = $x;
          }
        }
        
        foreach ($o_columns as $o2) {
          $s = "";
          foreach ($props as $prop) {
            $s .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.query.result.property",
              array("status" => $o2["o_" . strtolower($prop) . "_status"],
                "origvalue" => $this->safeHTML(Eisodos::$utils->safe_array_value($o2, "o_" . strtolower($prop) . "_origvalue")),
                "value" => $this->safeHTML($o2["o_" . strtolower($prop)]),
                "prop_name" => $prop),
              false);
          }
          $responseArray['html'] .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.query.result.main",
            array_merge($o2, array("properties" => $s)),
            false);
        }
        
        if (Eisodos::$parameterHandler->eq("todo", "save")) {
          
          $responseArray['html'] = "";
          //$responseArray['html']="<h4 > Current properties </h4 ><pre > ".print_r($o_columns,true)."</pre > ";
          $responseArray['html'] .= "<pre > ";
          
          $this->builder_db->startTransaction();
          
          foreach ($o_columns as $o2) {
            if (Eisodos::$utils->safe_array_value($o2, "status", "") == "new") { // create new component
              
              $boundVariables = [];
              $this->builder_db->bind($boundVariables, "p_id", "integer", "");
              $this->builder_db->bind($boundVariables, "p_parent_id", "integer", Eisodos::$parameterHandler->getParam("p_component_id"));
              $this->builder_db->bind($boundVariables, "p_component_type_id", "integer", $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "SELECT ID FROM DEF_COMPONENT_TYPES WHERE CLASS_NAME = 'TDBField'"));
              
              $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
              $this->builder_db->bind($boundVariables, "p_version", "integer", "");
              $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
              $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
              $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
              $resultArray = array();
              
              $this->builder_db->executeStoredProcedure(
                ($this->getDBObject($this->builder_db, "sp.component_insert")),
                $boundVariables,
                $resultArray
              );
              
              $this->SPError($resultArray);
              
              $o2["id"] = $resultArray["p_id"];
              
              $responseArray['html'] .= "Component created with ID: " . $o2["id"] . "\n";
              
            }
            
            if (Eisodos::$utils->safe_array_value($o2, "status", "") != "delete") {  // updating properties
              
              foreach ($props as $prop) {
                
                if ($o2["o_" . strtolower($prop) . "_status"] == "modify" || $o2["o_" . strtolower($prop) . "_status"] == "new") {
                  
                  $boundVariables = [];
                  $this->builder_db->bind($boundVariables, "p_id", "integer", $o2["o_" . strtolower($prop) . "_linkid"]);
                  $this->builder_db->bind($boundVariables, "p_component_id", "integer", $o2["id"]);
                  $this->builder_db->bind($boundVariables, "p_property_id", "integer", $o2["o_" . strtolower($prop) . "_propertyid"]);
                  $this->builder_db->bind($boundVariables, "p_value", "text", $o2["o_" . strtolower($prop)]);
                  $this->builder_db->bind($boundVariables, "p_value_component_id", "integer", "");
                  
                  $this->builder_db->bind($boundVariables, "p_version", "integer", Eisodos::$utils->safe_array_value($o2, "o_" . strtolower($prop) . "_version"));
                  $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
                  if ($o2["o_" . strtolower($prop) . "_linkid"] == "") $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
                  $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
                  $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
                  $resultArray = array();
                  
                  $this->builder_db->executeStoredProcedure(
                    ($this->getDBObject($this->builder_db, "sp.property_" . ($o2["o_" . strtolower($prop) . "_linkid"] == "" ? "insert" : "update"))),
                    $boundVariables,
                    $resultArray
                  );
                  
                  $this->SPError($resultArray);
                  
                  $responseArray['html'] .= "Component property " . ($o2["o_" . strtolower($prop) . "_linkid"] == "" ? "inserted" : "updated") . " with value: " . $o2["o_" . strtolower($prop)] . "\n";
                  
                }
              }
            } else
              $responseArray['html'] .= "\nComponent " . $o2["o_name"] . " must be deleted manually!\n\n";
            
            
          }
          
          $responseArray['html'] .= ($responseArray['html'] == "<pre > " ? "All components and properties are up to date . " : "") . " </pre > ";
          
          $this->builder_db->commit();
          
        } else
          $responseArray['html'] .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.query.result.foot", array(), false);
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          $responseArray['html'] = '<pre class="error">' . $responseArray['errormsg'] . '</pre>';
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showStoredProcedureWizard(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.storedprocedure.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function StoredProcedureWizardRun(): void {
      try {
        
        $o_columns = array();
        
        $component_Procedure = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select cpv.value from app_component_properties_v cpv where cpv.component_id = " . $this->builder_db->nullStrParam("p_component_id", false) . " and cpv.name = 'Procedure'");
        
        $boundVariables = [];
        if ($this->project_owner != "") {
          $this->project_db->bind($boundVariables, "p_owner", "text", $this->project_owner);
        }
        $this->project_db->bind($boundVariables, "p_procedure_name", "text", $component_Procedure);
        $this->project_db->bind($boundVariables, "p_arguments", "text", "");
        $this->project_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->project_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->project_db->startTransaction();
        $this->project_db->executeStoredProcedure(
          ($this->getDBObject($this->project_db, "sp.wizard_stored_procedure")),
          $boundVariables,
          $resultArray,
          true,
          CASE_LOWER
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        $responseArray['html'] = "";
        $columns = array();
        
        try {
          $columns = json_decode($resultArray["p_arguments"], true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
          $responseArray['html'] = "<h3 > Error converting to JSON!</h3 > " . $e->getMessage() . "<pre > " . $resultArray["p_arguments"] . "</pre > ";
        }
        
        try {
          
          $props = array();
          $props = ["ParameterName", "Name", "NativeDataType", "DataType", "ParameterMode"];
          
          $sql = "select tp.id, \n";
          
          foreach ($props as $prop) {
            $sql .= "cpv_" . $prop . " . value       as o_" . $prop . ", \n" .
              "cpv_" . $prop . " . link_id     as o_" . $prop . "_LinkId, \n" .
              "cpv_" . $prop . " . version     as o_" . $prop . "_Version, \n" .
              "cpv_" . $prop . " . property_id as o_" . $prop . "_PropertyId, \n" .
              "''                        as o_" . $prop . "_status, \n";
          }
          
          $sql .= "'delete' as status \n" .
            "  from app_tree_path_v tp \n";
          
          foreach ($props as $prop) {
            $sql .= "left outer join app_component_properties_v cpv_" . $prop . " on cpv_" . $prop . " . component_id = tp.id and cpv_" . $prop . " . name = '" . $prop . "' \n";
          }
          
          $sql .= " where tp.parent_id = " . $this->builder_db->nullStrParam("p_component_id", false) . " \n" .
            " and tp.class_name = 'TDBParam'";
          
          $this->builder_db->query(RT_ALL_ROWS, $sql, $o_columns);
          
        } catch (Exception $e) {
          $responseArray['html'] = "<h3 > Error getting current properties!</h3 > " . $e->getMessage();
        }
        
        // Eisodos::$logger->debug($columns);
        // Eisodos::$logger->debug($o_columns);
        
        // generating differences
        foreach ($columns as $column) {
          $found = false;
          foreach ($o_columns as &$o) {
            if (strtoupper($o["o_" . strtolower($props[0])]) == strtoupper($column[$props[0]])) {
              $found = true;
              $o["status"] = "uptodate";
              foreach ($props as $prop) {
                if ($o["o_" . strtolower($prop)] != $column[$prop]) {
                  $o["o_" . strtolower($prop) . "_origvalue"] = $o["o_" . strtolower($prop)];
                  $o["o_" . strtolower($prop)] = $column[$prop];
                  $o["o_" . strtolower($prop) . "_status"] = "modify";
                  $o["status"] = "modify";
                }
              }
            }
          }
          unset($o);
          if (!$found) {
            $x = array("id" => "", "status" => "new");
            foreach ($props as $prop) {
              $x["o_" . strtolower($prop)] = $column[$prop];
              $x["o_" . strtolower($prop) . "_linkid"] = "";
              $x["o_" . strtolower($prop) . "_origvalue"] = "";
              $x["o_" . strtolower($prop) . "_propertyid"] = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select id from DEF_PROPERTIES p where lower(p.name) = '" . strtolower($prop) . "'");
              $x["o_" . strtolower($prop) . "_status"] = "new";
            }
            $o_columns[] = $x;
          }
        }
        
        foreach ($o_columns as $o2) {
          $s = "";
          foreach ($props as $prop) {
            $s .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.storedprocedure.result.property",
              array("status" => $o2["o_" . strtolower($prop) . "_status"],
                "origvalue" => $this->safeHTML($o2["o_" . strtolower($prop) . "_origvalue"]),
                "value" => $this->safeHTML($o2["o_" . strtolower($prop)]),
                "prop_name" => $prop),
              false);
          }
          $responseArray['html'] .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.storedprocedure.result.main",
            array_merge($o2, array("properties" => $s)),
            false);
        }
        
        if (Eisodos::$parameterHandler->eq("todo", "save")) {
          
          $responseArray['html'] = "";
          //$responseArray['html']="<h4 > Current properties </h4 ><pre > ".print_r($o_columns,true)."</pre > ";
          $responseArray['html'] .= "<pre > ";
          
          $this->builder_db->startTransaction();
          
          foreach ($o_columns as $o2) {
            if (Eisodos::$utils->safe_array_value($o2, "status", "") == "new") { // create new component
              
              $boundVariables = [];
              $this->builder_db->bind($boundVariables, "p_id", "integer", "");
              $this->builder_db->bind($boundVariables, "p_parent_id", "integer", Eisodos::$parameterHandler->getParam("p_component_id"));
              $this->builder_db->bind($boundVariables, "p_component_type_id", "integer", $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "SELECT ID FROM DEF_COMPONENT_TYPES WHERE CLASS_NAME = 'TDBParam'"));
              
              $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
              $this->builder_db->bind($boundVariables, "p_version", "integer", "");
              $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
              $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
              $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
              $resultArray = array();
              
              $this->builder_db->executeStoredProcedure(
                ($this->getDBObject($this->builder_db, "sp.component_insert")),
                $boundVariables,
                $resultArray
              );
              
              $this->SPError($resultArray);
              
              $o2["id"] = $resultArray["p_id"];
              
              $responseArray['html'] .= "Component created with ID: " . $o2["id"] . "\n";
              
            }
            
            if (Eisodos::$utils->safe_array_value($o2, "status", "") != "delete") {  // updating properties
              
              foreach ($props as $prop) {
                
                if ($o2["o_" . strtolower($prop) . "_status"] == "modify" || $o2["o_" . strtolower($prop) . "_status"] == "new") {
                  
                  $boundVariables = [];
                  $this->builder_db->bind($boundVariables, "p_id", "integer", $o2["o_" . strtolower($prop) . "_linkid"]);
                  $this->builder_db->bind($boundVariables, "p_component_id", "integer", $o2["id"]);
                  $this->builder_db->bind($boundVariables, "p_property_id", "integer", $o2["o_" . strtolower($prop) . "_propertyid"]);
                  $this->builder_db->bind($boundVariables, "p_value", "text", $o2["o_" . strtolower($prop)]);
                  $this->builder_db->bind($boundVariables, "p_value_component_id", "integer", "");
                  
                  $this->builder_db->bind($boundVariables, "p_version", "integer", $o2["o_" . strtolower($prop) . "_version"]);
                  $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
                  if ($o2["o_" . strtolower($prop) . "_linkid"] == "") $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
                  $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
                  $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
                  $resultArray = array();
                  
                  $this->builder_db->executeStoredProcedure(
                    ($this->getDBObject($this->builder_db, "sp.property_" . ($o2["o_" . strtolower($prop) . "_linkid"] == "" ? "insert" : "update"))),
                    $boundVariables,
                    $resultArray
                  );
                  
                  $this->SPError($resultArray);
                  
                  $responseArray['html'] .= "Component property " . ($o2["o_" . strtolower($prop) . "_linkid"] == "" ? "inserted" : "updated") . " with value: " . $o2["o_" . strtolower($prop)] . "\n";
                  
                  // updating error_code, error_message, callback properties on TStoredProcedure
                  if ($prop == 'ParameterName') {
                    $sp_pname = '';
                    
                    if ($o2["o_" . strtolower($prop)] == "P_ERROR_CODE") {
                      $sp_pname = 'ErrorCodeParameter';
                    } elseif ($o2["o_" . strtolower($prop)] == "P_ERROR_MSG") {
                      $sp_pname = 'ErrorMessageParameter';
                    } elseif ($o2["o_" . strtolower($prop)] == "P_CALLBACK") {
                      $sp_pname = 'CallbackParameter';
                    } elseif ($o2["o_" . strtolower($prop)] == "P_LOGS") {
                      $sp_pname = 'LogParameter';
                    }
                    
                    if ($sp_pname != "") {
                      $sp_propid = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select nvl2(a.version, NULL, a.property_id) from app_component_properties_v a where a.component_id = " . Eisodos::$parameterHandler->getParam("p_component_id") . " and a.name = '" . $sp_pname . "'");
                      if ($sp_propid != "") { // csak ha nincs megadva
                        $boundVariables = [];
                        $this->builder_db->bind($boundVariables, "p_id", "integer", "");
                        $this->builder_db->bind($boundVariables, "p_component_id", "integer", Eisodos::$parameterHandler->getParam("p_component_id"));
                        $this->builder_db->bind($boundVariables, "p_property_id", "integer", $sp_propid);
                        $this->builder_db->bind($boundVariables, "p_value", "text", "");
                        $this->builder_db->bind($boundVariables, "p_value_component_id", "integer", $o2["id"]);
                        
                        $this->builder_db->bind($boundVariables, "p_version", "integer", "");
                        $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
                        $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
                        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
                        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
                        $resultArray2 = array();
                        
                        $this->builder_db->executeStoredProcedure(
                          ($this->getDBObject($this->builder_db, "sp.property_insert")),
                          $boundVariables,
                          $resultArray2
                        );
                        
                        $this->SPError($resultArray2);
                        
                        $responseArray['html'] .= "TStoredProcedure " . $sp_pname . " property was set to " . $o2["o_" . strtolower($prop)] . "\n";
                      }
                    }
                  }
                  
                  
                }
              }
            } else
              $responseArray['html'] .= "\nComponent " . $o2["o_name"] . " must be deleted manually!\n\n";
            
            
          }
          
          $responseArray['html'] .= ($responseArray['html'] == "<pre > " ? "All components and properties are up to date . " : "") . " </pre > ";
          
          $this->builder_db->commit();
          
        } else
          $responseArray['html'] .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.storedprocedure.result.foot", array(), false);
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showGridWizard(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.grid.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function GridWizardRun(): void {
      try {
        
        // getting fields
        
        $responseArray = [];
        
        $this->builder_db->startTransaction();
        
        $sql = "SELECT atp.id, \n" .
          "       atp.name, \n" .
          "       acp_datatype.value as datatype \n" .
          "  FROM app_tree_path_v atp \n" .
          "  LEFT OUTER JOIN app_component_properties_v acp_datatype ON acp_datatype.component_id = atp.id \n" .
          " and acp_datatype.l_name = 'datatype' \n" .
          " WHERE parent_id = (SELECT value_component_id \n" .
          "                      FROM app_component_properties_v acp \n" .
          "                     WHERE acp.component_id = " . $this->builder_db->nullStrParam("p_grid_id", false) . " \n" .
          " and acp.name = 'ListSource') \n" .
          " and atp.class_name = 'TDBField' \n" .
          " and atp.id NOT IN \n" .
          "       (SELECT value_component_id \n" .
          "          FROM app_component_properties_v acp \n" .
          "         WHERE acp.component_id IN(SELECT atp.id \n" .
          "                                      FROM app_tree_path_v atp \n" .
          "                                     WHERE parent_id = " . $this->builder_db->nullStrParam("p_grid_id", false) . " \n" .
          " and atp.class_name = 'TGridColumn') \n" .
          " and acp.name = 'DBField' \n" .
          " and VALUE IS NOT NULL) \n" .
          " ORDER BY atp.component_order";
        
        $dbFields = array();
        $this->builder_db->query(RT_ALL_ROWS, $sql, $dbFields);
        
        $properties = array();
        $gridcolumns = array();
        
        foreach ($dbFields as $dbField) {
          if (Eisodos::$parameterHandler->eq("option" . $dbField["id"], "3") || Eisodos::$parameterHandler->eq("option" . $dbField["id"], "2") || Eisodos::$parameterHandler->eq("option" . $dbField["id"], "1")) { // creating column
            $boundVariables = [];
            $this->builder_db->bind($boundVariables, "p_id", "integer", "");
            $this->builder_db->bind($boundVariables, "p_parent_id", "integer", Eisodos::$parameterHandler->getParam("p_grid_id"));
            $this->builder_db->bind($boundVariables, "p_component_type_id", "integer", $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "SELECT ID FROM DEF_COMPONENT_TYPES WHERE CLASS_NAME = 'TGridColumn'"));
            
            $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_version", "integer", "");
            $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
            $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
            $resultArray = array();
            
            $this->builder_db->executeStoredProcedure(
              ($this->getDBObject($this->builder_db, "sp.component_insert")),
              $boundVariables,
              $resultArray
            );
            
            $this->SPError($resultArray);
            
            $control = $resultArray["p_id"];
            $gridcolumns[$dbField["name"]] = $control;
            $properties[$control]["Name"] = array("value" => "gc" . $dbField["name"]);
            $properties[$control]["DBField"] = array("value_component_id" => $dbField["id"]);
            if ($dbField["datatype"] == "integer" || $dbField["datatype"] == "float")
              $properties[$control]["Align"] = array("value" => "right");
            elseif ($dbField["datatype"] == "date" || $dbField["datatype"] == "datetime" || $dbField["datatype"] == "time")
              $properties[$control]["Align"] = array("value" => "center");
            elseif ($dbField["datatype"] == "bool") {
              $properties[$control]["Align"] = array("value" => "center");
              $properties[$control]["ValueTemplate"] = array("value" => "grid.column.bool");
            }
            if (Eisodos::$parameterHandler->eq("option" . $dbField["id"], "3")) {
              $properties[$control]["Visible"] = array("value" => "false");
            }
            
            $responseArray['html'] .= "GridColumn " . "gc" . $dbField["name"] . " created with ID: " . $control . "\n";
            
          }
        }
        
        foreach ($dbFields as $dbField) {
          if (Eisodos::$parameterHandler->eq("option" . $dbField["id"], "2")) { // creating filters
            
            $boundVariables = [];
            $this->builder_db->bind($boundVariables, "p_id", "integer", "");
            $this->builder_db->bind($boundVariables, "p_parent_id", "integer", Eisodos::$parameterHandler->getParam("p_grid_id"));
            $this->builder_db->bind($boundVariables, "p_component_type_id", "integer", $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "SELECT ID FROM DEF_COMPONENT_TYPES WHERE CLASS_NAME = 'TGridFilter'"));
            
            $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_version", "integer", "");
            $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
            $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
            $resultArray = array();
            
            $this->builder_db->executeStoredProcedure(
              ($this->getDBObject($this->builder_db, "sp.component_insert")),
              $boundVariables,
              $resultArray
            );
            
            $this->SPError($resultArray);
            
            $gridfilter = $resultArray["p_id"];
            
            $properties[$gridfilter]["Name"] = array("value" => "filter" . $dbField["name"]);
            $properties[$gridfilter]["DBField"] = array("value_component_id" => $dbField["id"]);
            $properties[$gridcolumns[$dbField["name"]]]["GridFilter"] = array("value_component_id" => $gridfilter);
            
            if ($dbField["name"] == "ENABLED" || $dbField["name"] == "ACTIVE_IND") { // TODO kivezetni paraméterbe
              $properties[$gridfilter]["DefaultRelation"] = array("value" => "eq");
              $properties[$gridfilter]["Value"] = array("value" => "Y");
            }
            
            $responseArray['html'] .= "GridFilter " . "filter" . $dbField["name"] . " created with ID: " . $gridfilter . "\n";
            
          }
        }
        
        /* finding controls in TGrid with empty DBField property and set it to ID */
        
        if (Eisodos::$parameterHandler->neq('GridWizardSkipIDs', 'T')) {
          
          $sql = "select atp.id \n" .
            "  from app_tree_path_v atp \n" .
            " where atp.parent_id = (\n" .
            "select acp2.value_component_id \n" .
            "  from app_component_properties_v acp2 \n" .
            " where acp2.component_id = " . $this->builder_db->nullStrParam("p_grid_id", false) . " \n" .
            " and acp2.name = 'ListSource' \n" .
            " ) \n" .
            " and atp.class_name = 'TDBField' \n" .
            " and atp.name = 'ID'";
          
          $keyfieldID = $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, $sql);
          
          $sql = "select id \n" .
            "  from app_tree_path_v atp, \n" .
            "       app_component_properties_v acp \n" .
            " where path like(select path || '_%' from app_tree_path_v atp2 where atp2.id = " . $this->builder_db->nullStrParam("p_grid_id", false) . ") \n" .
            " and acp.component_id = atp.id \n" .
            " and acp.name = 'DBField' \n" .
            " and acp.value_component_id is null \n" .
            " order by id";
          
          $controls = array();
          $this->builder_db->query(RT_ALL_FIRST_COLUMN_VALUES, $sql, $controls);
          
          if ($keyfieldID != "" and count($controls) > 0) {
            foreach ($controls as $control) {
              $properties[$control]["DBField"] = array("value_component_id" => $keyfieldID);
            }
          }
          
        }
        
        
        foreach ($properties as $component_id => $property) { // creating properties
          
          foreach ($property as $property_name => $params) {
            
            $boundVariables = [];
            $this->builder_db->bind($boundVariables, "p_id", "integer", "");
            $this->builder_db->bind($boundVariables, "p_component_id", "integer", $component_id);
            $this->builder_db->bind($boundVariables, "p_property_id", "integer", $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select id from DEF_PROPERTIES dp where dp.name = " . n($property_name)));
            $this->builder_db->bind($boundVariables, "p_value", "text", Eisodos::$utils->safe_array_value($params, "value", ""));
            $this->builder_db->bind($boundVariables, "p_value_component_id", "integer", Eisodos::$utils->safe_array_value($params, "value_component_id", ""));
            
            $this->builder_db->bind($boundVariables, "p_version", "integer", "");
            $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
            $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
            $resultArray = array();
            
            $this->builder_db->executeStoredProcedure(
              ($this->getDBObject($this->builder_db, "sp.property_insert")),
              $boundVariables,
              $resultArray
            );
            
            $this->SPError($resultArray);
            
            $responseArray['html'] .= "Property " . $property_name . " created with value / value_component_id: " . Eisodos::$utils->safe_array_value($params, "value", "") . Eisodos::$utils->safe_array_value($params, "value_component_id", "") . "\n";
            
          }
        }
        
        $responseArray['success'] = 'OK';
        $responseArray['html'] = "<pre > " . $responseArray['html'] . "</pre > ";
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          $responseArray['html'] = "<pre > " . $responseArray['errormsg'] . "</pre > ";
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showEditFormWizard(): void {
      try {
        
        $responseArray['success'] = 'OK';
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.editform.main",
          array('EditFormWizardBlacklist' => Eisodos::$parameterHandler->getParam("p_EditFormWizardBlacklist", Eisodos::$parameterHandler->getParam("TholosBuilder.EditFormWizardBlacklist"))), false);
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          $responseArray['html'] = '<pre class="error">' . $responseArray['errormsg'] . '</pre>';
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function EditFormWizardRun(): void {
      try {
        
        // getting fields
        
        $this->builder_db->startTransaction();
        
        $sql = "SELECT atp.id, \n" .
          "       atp.name, \n" .
          "       acp_datatype.value as datatype, \n" .
          "       acp_size.value as datasize \n" .
          "  FROM app_tree_path_v atp \n" .
          "  LEFT OUTER JOIN app_component_properties_v acp_datatype ON acp_datatype.component_id = atp.id \n" .
          " and acp_datatype.l_name = 'datatype' \n" .
          "  LEFT OUTER JOIN app_component_properties_v acp_size ON acp_size.component_id = atp.id \n" .
          " and acp_size.l_name = 'size' \n" .
          " WHERE parent_id = " . $this->builder_db->nullStrParam("p_query_id", false) . " \n" .
          " and atp.class_name = 'TDBField' \n" .
          " and atp.id NOT IN \n" .
          "       (SELECT value_component_id \n" .
          "          FROM app_component_properties_v acp \n" .
          "         WHERE acp.component_id IN(SELECT atp.id \n" .
          "                                      FROM app_tree_path_v atp \n" .
          "                                     WHERE parent_id = " . $this->builder_db->nullStrParam("p_form_id", false) . ") \n" .
          " and acp.name = 'DBField' \n" .
          " and VALUE IS NOT NULL) \n" .
          " ORDER BY atp.component_order";
        
        
        $dbFields = array();
        $this->builder_db->query(RT_ALL_ROWS, $sql, $dbFields);
        
        $properties = array();
        
        foreach ($dbFields as $dbField) {
          if (Eisodos::$parameterHandler->neq("option" . $dbField["id"], "")) { // creating control
            $boundVariables = [];
            $this->builder_db->bind($boundVariables, "p_id", "integer", "");
            $this->builder_db->bind($boundVariables, "p_parent_id", "integer", Eisodos::$parameterHandler->getParam("p_form_id"));
            $this->builder_db->bind($boundVariables, "p_component_type_id", "integer", $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select id from def_component_types where class_name = " . $this->builder_db->nullStrParam("option" . $dbField["id"])));
            
            $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_version", "integer", "");
            $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
            $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
            $resultArray = array();
            
            $this->builder_db->executeStoredProcedure(
              ($this->getDBObject($this->builder_db, "sp.component_insert")),
              $boundVariables,
              $resultArray
            );
            
            $this->SPError($resultArray);
            
            $control = $resultArray["p_id"];
            
            $prefix = "";
            switch (Eisodos::$parameterHandler->getParam("option" . $dbField["id"])) {
              case "TEdit" :
                $prefix = "ed";
                break;
              case "TDateTimePicker" :
                $prefix = "dt";
                break;
              case "TCheckbox" :
                $prefix = "cb";
                break;
              case "TLOV" :
                $prefix = "lov";
                break;
              case "TText" :
                $prefix = "text";
                break;
              case "THTMLEdit" :
                $prefix = "html";
                break;
              case "TLabel" :
                $prefix = "label";
                break;
              case "TRadio" :
                $prefix = "radio";
                break;
              case "TStatic" :
                $prefix = "s";
                break;
              case "THidden" :
                $prefix = "h";
                break;
            }
            
            $properties[$control]["Name"] = array("value" => $prefix . $dbField["name"]);
            $properties[$control]["DBField"] = array("value_component_id" => $dbField["id"]);
            
            $responseArray = [];
            
            if (Eisodos::$parameterHandler->eq("option" . $dbField["id"], "TEdit")) {
              if ($dbField["datatype"] == "integer" || $dbField["datatype"] == "float")
                $properties[$control]["HTMLInputType"] = array("value" => "number");
              elseif ($dbField["datatype"] == "string" || $dbField["datatype"] == "text")
                $properties[$control]["HTMLInputType"] = array("value" => "text");
            } elseif (Eisodos::$parameterHandler->eq("option" . $dbField["id"], "TDateTimePicker")) {
              if ($dbField["datatype"] == "date")
                $properties[$control]["JSDateTimeFormat"] = array("value" => "@(parameter) . JSDateFormat");
              elseif ($dbField["datatype"] == "datetime")
                $properties[$control]["JSDateTimeFormat"] = array("value" => "@(parameter) . JSDateTimeFormat");
              elseif ($dbField["datatype"] == "datetimehm")
                $properties[$control]["JSDateTimeFormat"] = array("value" => "@(parameter) . JSDateTimeHMFormat");
              elseif ($dbField["datatype"] == "time")
                $properties[$control]["JSDateTimeFormat"] = array("value" => "@(parameter) . JSTimeFormat");
              elseif ($dbField["datatype"] == "timestamp")
                $properties[$control]["JSDateTimeFormat"] = array("value" => "@(parameter) . JSTimestampFormat");
              else $responseArray['html'] .= "!!!" . $properties[$control]["Name"] . ":TDateTimePicker is referring to a non - date type field!\n";
            } elseif (Eisodos::$parameterHandler->eq("option" . $dbField["id"], "TText")) {
              $properties[$control]["Autosize"] = array("value" => "true");
            } elseif (Eisodos::$parameterHandler->eq("option" . $dbField["id"], "TCheckbox")) {
              $properties[$control]["class"] = array("value" => "inverted");
            }
            
            $responseArray['html'] .= "Control " . $prefix . $dbField["name"] . " created with ID: " . $control . "\n";
            
          }
        }
        
        foreach ($properties as $component_id => $property) { // creating properties
          
          foreach ($property as $property_name => $params) {
            
            $boundVariables = [];
            $this->builder_db->bind($boundVariables, "p_id", "integer", "");
            $this->builder_db->bind($boundVariables, "p_component_id", "integer", $component_id);
            $this->builder_db->bind($boundVariables, "p_property_id", "integer", $this->builder_db->query(RT_FIRST_ROW_FIRST_COLUMN, "select id from DEF_PROPERTIES dp where dp.name = " . n($property_name)));
            $this->builder_db->bind($boundVariables, "p_value", "text", Eisodos::$utils->safe_array_value($params, "value", ""));
            $this->builder_db->bind($boundVariables, "p_value_component_id", "integer", Eisodos::$utils->safe_array_value($params, "value_component_id", ""));
            
            $this->builder_db->bind($boundVariables, "p_version", "integer", "");
            $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_change", "text", "Y");
            $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
            $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
            $resultArray = array();
            
            $this->builder_db->executeStoredProcedure(
              ($this->getDBObject($this->builder_db, "sp.property_insert")),
              $boundVariables,
              $resultArray
            );
            
            $this->SPError($resultArray);
            
            $responseArray['html'] .= "Property " . $property_name . " created with value / value_component_id: " . Eisodos::$utils->safe_array_value($params, "value", "") . Eisodos::$utils->safe_array_value($params, "value_component_id", "") . "\n";
            
          }
        }
        
        $responseArray['success'] = 'OK';
        $responseArray['html'] = "<pre > " . $responseArray['html'] . "</pre > ";
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        $responseArray['success'] = 'ERROR';
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          $responseArray['html'] = '<pre class="error">' . $responseArray['errormsg'] . '</pre>';
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showEditHelp(): void {
      $responseArray['success'] = 'OK';
      $back = array();
      if (Eisodos::$parameterHandler->neq('p_help_id', '')) {
        $this->builder_db->query(RT_FIRST_ROW, "select id,version,text from app_help where id = " . $this->builder_db->nullStrParam("p_help_id", false), $back);
      }
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.help.main", $back, false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showHelpInfo(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "propframe.help.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function saveHelp(): void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        $this->builder_db->bindParam($boundVariables, "p_version", "integer");
        $this->builder_db->bindParam($boundVariables, "p_component_id", "integer");
        $this->builder_db->bindParam($boundVariables, "p_text", "clob");
        $this->builder_db->bindParam($boundVariables, "p_help_id", "integer");
        
        $this->builder_db->bind($boundVariables, "p_enabled", "text", "Y");
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.help_" . (Eisodos::$parameterHandler->eq("p_id", "") ? "insert" : "update"))),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        $responseArray['p_id'] = $resultArray["p_id"];
        $responseArray['p_version'] = $resultArray["p_version"];
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function deleteHelp(): void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_id", "integer");
        $this->builder_db->bindParam($boundVariables, "p_version", "integer");
        
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.help_delete")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function generateUserGuide(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "userhelp.main", array(), false);
      if (Eisodos::$parameterHandler->neq("Tholos.GenerateHelpFile", "")) {
        $helpFile = fopen(Eisodos::$parameterHandler->getParam("TholosBuilder.GenerateHelpFile"), 'wb');
        fwrite($helpFile, Eisodos::$templateEngine->getTemplate($this->templateFolder . "userhelp.generated", array("userguide" => $responseArray['html']), false));
        fclose($helpFile);
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showTranslate(): void {
      Eisodos::$parameterHandler->setParam("TranslateLanguageTags", "T");
      Eisodos::$parameterHandler->setParam("CollectLangIDs", "T");
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "translate.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    private function openTask(): void {
      try {
        
        $boundVariables = [];
        $this->builder_db->bindParam($boundVariables, "p_task_number", "integer");
        $this->builder_db->bindParam($boundVariables, "p_subject", "text");
        $this->builder_db->bindParam($boundVariables, "p_project_id", "text");
        
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.task_open")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function closeTask(): void {
      try {
        
        $boundVariables = [];
        
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.task_close")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showOpenedTasks(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.commit.status", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function commitChanges(): void {
      
      require_once 'vendor_php/redmine-1.5.15/autoload.php';
      
      $redmine_options = array();
      
      $this->builder_db->query(RT_FIRST_ROW, "select rm_secretkey, rm_project_id, rm_subprojects \n" .
        "  from app_users \n" .
        " where id = app_session_pkg.user_id",
        $redmine_options);
      
      if (Eisodos::$utils->safe_array_value($redmine_options, "rm_secretkey", "") == "")
        throw new RuntimeException("No Redmine secret key defined in user profile");
      
      $redmine = new Client(Eisodos::$parameterHandler->getParam("TholosBuilder.RedmineURL", ""), $redmine_options["rm_secretkey"]);
      
      $responseArray['success'] = 'OK';
      
      $sql = "SELECT listagg(route, ',') within GROUP(ORDER BY 1) as routes, \n" .
        "       listagg('#' || task_number, ', ') within GROUP(ORDER BY 1) as tasks, \n" .
        "       listagg(task_number, ', ') within GROUP(ORDER BY 1) as task_ids \n" .
        "  FROM(SELECT DISTINCT atp.route, \n" .
        "                        at.task_number \n" .
        "          FROM app_changes     ac, \n" .
        "               app_tree_path_v atp, \n" .
        "               app_tasks       at, \n" .
        "               app_users       au \n" .
        "         WHERE ac.component_id = atp.id \n" .
        " and at.id = ac.task_id \n" .
        " and au.id = at.created_by \n" .
        " and at.committed IS NULL \n" .
        " and at.closed = 'N' \n" .
        " and au.id = app_session_pkg.user_id) \n";
      
      $back = array();
      
      $this->builder_db->query(RT_FIRST_ROW, $sql, $back);
      
      $task_ = $redmine->issue->show($back["task_ids"]);
      
      $rmmembers = "";
      $a_ = $redmine->membership->all($task_["issue"]["project"]["id"]);
      foreach ($a_["memberships"] as $member) {
        $rmmembers .= '<option value="' . (array_key_exists("user", $member) ? $member["user"]["id"] : ($member["group"]["id"])) . '" ' . ((array_key_exists("user", $member) ? $member["user"]["id"] : ($member["group"]["id"])) == $task_["issue"]["assigned_to"]["id"] ? "selected" : "") . '>' . (array_key_exists("user", $member) ? $member["user"]["name"] : ("[" . $member["group"]["name"] . "]")) . '</option>';
      }
      
      $redmineStatuses = "";
      $a_ = $redmine->issue_status->all(array("project_id" => $task_["issue"]["project"]["id"]));
      foreach ($a_["issue_statuses"] as $status) {
        $redmineStatuses .= '<option value="' . $status["id"] . '" ' . ($status["id"] == $task_["issue"]["status"]["id"] ? "selected" : "") . '>' . $status["name"] . '</option>';
      }
      
      $rmactivities = "";
      $a_ = $redmine->time_entry_activity->all(array("project_id" => $task_["issue"]["project"]["id"]));
      foreach ($a_["time_entry_activities"] as $activity) {
        $rmactivities .= '<option value="' . $activity["id"] . '" ' . ($activity["name"] == "Fejlesztés" ? "selected" : "") . '>' . $activity["name"] . '</option>';
      }
      
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.commit.main",
        array_merge($back,
          array("rmmembers" => $rmmembers,
            "rmstatuses" => $redmineStatuses,
            "rmactivities" => $rmactivities,
            "rmassigned_to" => $task_["issue"]["assigned_to"]["id"],
            "rmstatus" => $task_["issue"]["status"]["id"])),
        false);
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function saveCommitChanges(): void {
      try {
        
        $responseArray['success'] = 'OK';
        
        $tcsfiles = "";
        
        if (Eisodos::$parameterHandler->eq("p_message", "")) throw new RuntimeException("Message can not be empty");
        
        $result = "";
        foreach (array_unique(explode(",", Eisodos::$parameterHandler->getParam("p_routes"))) as $route) {
          $sourcefile = Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceWorkingDir") . Eisodos::$parameterHandler->getParam("user_name", "") . "/" . $route . ".tcs";
          $destfile = Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceDir") . $route . ".tcs";
          if (file_exists($sourcefile)) {
            copy($sourcefile, $destfile);
            $result .= "Copying\n" . Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceWorkingDir") . Eisodos::$parameterHandler->getParam("user_name", "") . "/" . $route . ".tcs to\n" .
              Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceDir") . $route . ".tcs" . "\n";
            $tcsfiles .= Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceDir") . $route . ".tcs";
          }
        }
        
        if ($tcsfiles == "") throw new RuntimeException("Nothing to commit");
        
        if (!mkdir($commitLogDirectory = Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceWorkingDir") . Eisodos::$parameterHandler->getParam("user_name", "") . "/commit-log") && !is_dir($commitLogDirectory)) {
          throw new \RuntimeException(sprintf('Directory "%s" was not created', $commitLogDirectory));
        }
        $logfile = Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceWorkingDir") . Eisodos::$parameterHandler->getParam("user_name", "") . "/commit-log/ " . date("YmdHis") . ".msg";
        $result .= "\nWriting message file\n  " . $logfile . "\n";
        $file = fopen($logfile, "wb");
        if ($file === false) throw new RuntimeException("Can not write log file");
        fwrite($file, Eisodos::$parameterHandler->getParam("p_message"));
        fclose($file);
        
        $result .= "\nGetting SVN authorization information\n";
        
        $sql = "SELECT SVN_USERNAME, SVN_PASSWORD FROM APP_USERS WHERE ID = APP_SESSION_PKG.USER_ID AND SVN_USERNAME IS NOT NULL AND SVN_PASSWORD IS NOT NULL";
        $svn = array();
        if (!$this->builder_db->query(RT_FIRST_ROW, $sql, $svn)) throw new RuntimeException("You are not logged in!");
        
        $result .= "  Done.\n";
        
        $svncommand1 = "cd " . Eisodos::$parameterHandler->getParam("TholosBuilder.ApplicationSourceDir") . ' && svn st | grep ? | cut -d? -f2 | xargs svn add';
        $svncommand2 = Eisodos::$parameterHandler->getParam("TholosBuilder.SVN", "/bin/svn") . ' commit --non-interactive --no-auth-cache --username ' . $svn["svn_username"] . ' --password ' . $svn["svn_password"] .
          ' --file ' . $logfile .
          ' ' . $tcsfiles . ' 2>&1';
        
        $result .= "\nRunning svn commands\n\n" .
          $svncommand1 . "\n\n";
        
        $output = shell_exec($svncommand1);
        
        $result .= $output . "\n\n";
        
        $result .= $svncommand2 . "\n\n";
        
        $output = shell_exec($svncommand2);
        
        $result .= $output . "\n\n";
        
        if (D_pos("Commit failed", $output)) throw new RuntimeException("Commit failed");
        
        $result .= "Setting commit flag for the tasks\n\n";
        
        $sql = "UPDATE app_tasks at2 \n" .
          "   SET COMMITTED = SYSDATE, \n" .
          "       closed = 'Y' \n" .
          " WHERE at2.id IN(SELECT at.id \n" .
          "                    FROM app_changes     ac, \n" .
          "                         app_tree_path_v atp, \n" .
          "                         app_tasks       at, \n" .
          "                         app_users       au \n" .
          "                   WHERE ac.component_id = atp.id \n" .
          " and at.id = ac.task_id \n" .
          " and au.id = at.created_by \n" .
          " and at.committed IS NULL \n" .
          " and at.closed = 'N' \n" .
          " and au.id = app_session_pkg.user_id) \n";
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeDML($sql);
        $this->builder_db->commit();
        
        $result .= "Redmine processes\n\n";
        
        $redmine_options = array();
        
        $this->builder_db->query(RT_FIRST_ROW, "select rm_secretkey, rm_project_id, rm_subprojects \n" .
          "  from app_users \n" .
          " where id = app_session_pkg.user_id",
          $redmine_options);
        
        if (Eisodos::$utils->safe_array_value($redmine_options, "rm_secretkey", "") == "")
          throw new RuntimeException("No Redmine secret key defined in user profile");
        
        $redmine = new Client(Eisodos::$parameterHandler->getParam("TholosBuilder.RedmineURL", ""), $redmine_options["rm_secretkey"]);
        
        try {
          $taskoptions = array();
          if (Eisodos::$parameterHandler->getParam("p_rm_assigned_to", "") != Eisodos::$parameterHandler->getParam("p_rm_current_assigned_to"))
            $taskoptions["assigned_to_id"] = Eisodos::$parameterHandler->getParam("p_rm_assigned_to", "");
          
          if (Eisodos::$parameterHandler->getParam("p_rm_status", "") != Eisodos::$parameterHandler->getParam("p_rm_current_status"))
            $taskoptions["status_id"] = Eisodos::$parameterHandler->getParam("p_rm_status", "");
          
          if (Eisodos::$parameterHandler->neq("p_rm_note", ""))
            $taskoptions["notes"] = Eisodos::$parameterHandler->getParam("p_rm_note", "");
          
          if (!empty($taskoptions)) {
            $result .= "Updating redmine task... ";
            $redmine->issue->update(Eisodos::$parameterHandler->getParam("task_number"), $taskoptions);
            $result .= "Success\n\n";
          }
          
        } catch (Exception $e) {
          $result .= "Failed: " . $e->getMessage() . "\n\n";
        }
        
        try {
          $timeoptions = array();
          if (Eisodos::$parameterHandler->neq("p_rm_time_spent", "")) {
            $result .= "Saving time record... ";
            if (!D_isfloat(Eisodos::$parameterHandler->getParam("p_rm_time_spent"))) throw new RuntimeException("Spent time is not a number!");
            
            $timeoptions["hours"] = Eisodos::$parameterHandler->getParam("p_rm_time_spent");
            $timeoptions["issue_id"] = Eisodos::$parameterHandler->getParam("task_number");
            $timeoptions["activity_id"] = Eisodos::$parameterHandler->getParam("p_rm_time_activity");
            
            $redmine->time_entry->create($timeoptions);
            
            $result .= "Success\n\n";
          }
          
        } catch (Exception $e) {
          $result .= "Failed: " . $e->getMessage() . "\n\n";
        }
        
        $result .= "Commit done.";
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $result .= "\n\n\nERROR: " . $e->getMessage() . "\n\n\n";
      }
      
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.commit.result",
        array("result" => " < pre>" . $result . " </pre > "),
        false);
      
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function chooseTask(): void {
      
      require_once 'vendor_php/redmine-1.5.15/autoload.php';
      
      try {
        
        $redmine_options = array();
        
        $this->builder_db->query(RT_FIRST_ROW, "select rm_secretkey, rm_project_id, rm_subprojects \n" .
          "  from app_users \n" .
          " where id = app_session_pkg.user_id",
          $redmine_options);
        
        if (Eisodos::$utils->safe_array_value($redmine_options, "rm_secretkey", "") == "")
          throw new RuntimeException("No Redmine secret key defined in user profile");
        
        $redmine = new Client(Eisodos::$parameterHandler->getParam("TholosBuilder.RedmineURL", ""), $redmine_options["rm_secretkey"]);
        
        if (Eisodos::$parameterHandler->neq("p_rm_task_id", ""))
          $a_ = $redmine->issue->all(['issue_id' => Eisodos::$parameterHandler->getParam("p_rm_task_id", "")
          ]);
        else
          $a_ = $redmine->issue->all(['status_id' => Eisodos::$parameterHandler->getParam("p_rm_status", ""),
            'sort' => 'project_id,id:desc',
            'project_id' => Eisodos::$parameterHandler->getParam("p_rm_project_id", explode(',', $redmine_options["rm_project_id"])[0]),
            'subproject_id' => Eisodos::$parameterHandler->getParam("p_rm_subprojects", Eisodos::$utils->safe_array_value($redmine_options, "rm_subprojects", '*')),
            'limit' => '1000',
            'assigned_to_id' => Eisodos::$parameterHandler->getParam("p_rm_assigned_to_id", ""),
            'fixed_version_id' => Eisodos::$parameterHandler->getParam("p_rm_version", "")
          ]);
        
        $issues = "";
        foreach ($a_["issues"] as $task) {
          $issues .= Eisodos::$templateEngine->getTemplate("tholosbuilder / redmine.issues.list.row",
            array("id" => $task["id"],
              "project" => $task["project"]["name"],
              "tracker" => $task["tracker"]["name"],
              "status" => $task["status"]["name"],
              "category" => $task["category"]["name"],
              "assigned_to" => $task["assigned_to"]["name"],
              "subject" => Eisodos::$utils->replace_all($task["subject"], "'", "")
            ),
            false);
        }
        
        $rmprojects = "";
        foreach (explode(',', $redmine_options["rm_project_id"]) as $p_) {
          $rmprojects .= ' < option value = "' . $p_ . '" ' . (Eisodos::$parameterHandler->getParam("p_rm_project_id", explode(',', $redmine_options["rm_project_id"])[0]) == $p_ ? 'selected' : '') . ' > ' . $p_ . '</option > ';
        }
        
        $rmmembers = "";
        $a_ = $redmine->membership->all(Eisodos::$parameterHandler->getParam("p_rm_project_id", explode(',', $redmine_options["rm_project_id"])[0]), array("sort" => "name"));
        foreach ($a_["memberships"] as $member) {
          $rmmembers .= ' < option value = "' . (array_key_exists("user", $member) ? $member["user"]["id"] : ($member["group"]["id"])) . '" ' . ((array_key_exists("user", $member) ? $member["user"]["id"] : ($member["group"]["id"])) == Eisodos::$parameterHandler->getParam("p_rm_assigned_to_id", "me") ? "selected" : "") . ' > ' . (array_key_exists("user", $member) ? $member["user"]["name"] : ("[" . $member["group"]["name"] . "]")) . '</option > ';
        }
        
        $redmineStatuses = "";
        $a_ = $redmine->issue_status->all(array("project_id" => Eisodos::$parameterHandler->getParam("p_rm_project_id", explode(',', $redmine_options["rm_project_id"])[0])));
        foreach ($a_["issue_statuses"] as $status) {
          $redmineStatuses .= ' < option value = "' . $status["id"] . '" ' . ($status["id"] == Eisodos::$parameterHandler->getParam("p_rm_status", "open") ? "selected" : "") . ' > ' . $status["name"] . '</option > ';
        }
        
        $redmineVersions = "";
        $a_ = $redmine->version->all(Eisodos::$parameterHandler->getParam("p_rm_project_id", explode(',', $redmine_options["rm_project_id"])[0]));
        foreach ($a_["versions"] as $version) {
          if ($version["status"] == "open")
            $redmineVersions .= ' < option value = "' . $version["id"] . '" ' . ($version["id"] == Eisodos::$parameterHandler->getParam("p_rm_version", "") ? "selected" : "") . ' > ' . $version["name"] . '</option > ';
        }
        
        $responseArray['success'] = 'OK';
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate("tholosbuilder/redmine.issues.list.main",
          array_merge(array("issues" => $issues,
            "rmprojects" => $rmprojects,
            "rmmembers" => $rmmembers,
            "rmstatuses" => $redmineStatuses,
            "rmversions" => $redmineVersions),
            $redmine_options),
          false);
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function reloadTaskFrame(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "taskframe.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showCommitHistory(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.commit.history.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function showUserProfile(): void {
      $responseArray['success'] = 'OK';
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.userprofile.main", array(), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function saveUserProfile(): void {
      try {
        
        $boundVariables = [];
        
        $this->builder_db->bindParam($boundVariables, "p_svn_username", "text");
        $this->builder_db->bindParam($boundVariables, "p_svn_password", "text");
        $this->builder_db->bindParam($boundVariables, "p_rm_secretkey", "text");
        $this->builder_db->bindParam($boundVariables, "p_rm_project_id", "text");
        $this->builder_db->bindParam($boundVariables, "p_rm_subprojects", "text");
        
        $this->builder_db->bind($boundVariables, "p_error_msg", "text", "");
        $this->builder_db->bind($boundVariables, "p_error_code", "integer", "");
        $resultArray = array();
        
        $this->builder_db->startTransaction();
        $this->builder_db->executeStoredProcedure(
          ($this->getDBObject($this->builder_db, "sp.user_config")),
          $boundVariables,
          $resultArray
        );
        
        $this->SPError($resultArray);
        
        $responseArray['success'] = 'OK';
        
        $this->builder_db->commit();
        
        $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "wizards.userprofile.main", array(), false);
        
      } catch (Exception $e) {
        
        if ($this->builder_db->inTransaction()) {
          $this->builder_db->rollback();
        }
        
        if ($e->getMessage() != "") {
          $responseArray['errormsg'] = $e->getMessage();
          if (Eisodos::$parameterHandler->neq('SPError', 'T')) {
            Eisodos::$logger->writeErrorLog($e);
          }
        }
        
        $responseArray['success'] = 'ERROR';
        
      }
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finishRaw();
      exit;
    }
    
    private function addRoute(): void {
      $responseArray['success'] = 'OK';
      $route_filter = explode(",", Eisodos::$parameterHandler->getParam("route_filter", ""));
      if (!in_array(Eisodos::$parameterHandler->getParam("route_id"), $route_filter)) {
        $route_filter[] = Eisodos::$parameterHandler->getParam("route_id");
        Eisodos::$parameterHandler->setParam("route_filter", implode(",", $route_filter), true);
      }
      $responseArray['html'] = '';
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    /**
     * @throws JsonException
     */
    #[NoReturn]
    private function showRoutes(): void {
      $responseArray['success'] = 'OK';
      $sql = "select id, name \n" .
        " from app_tree_v a \n" .
        " where class_name='TRoute' \n" .
        "       or (a.class_name <> 'TRoute' and parent_id=(select id from app_components ac where ac.parent_id is null)) \n" .
        " order by lower(name)";
      $back = array();
      $route_filter = array();
      $route_filter[] = "-1";
      $this->builder_db->query(RT_ALL_ROWS, $sql, $back);
      if (Eisodos::$parameterHandler->eq("todo", "save")) {
        foreach ($back as $row) {
          if (Eisodos::$parameterHandler->eq("filter_route_" . $row["id"], "T")) {
            $route_filter[] = $row["id"];
          }
        }
        Eisodos::$parameterHandler->setParam("route_filter", implode(",", $route_filter), true);
      }
      $route_filter = explode(",", Eisodos::$parameterHandler->getParam("route_filter", ""));
      if (Eisodos::$parameterHandler->eq("todo", "addRoute") && !in_array(Eisodos::$parameterHandler->getParam("route_id"), $route_filter)) {
        $route_filter[] = Eisodos::$parameterHandler->getParam("route_id");
        Eisodos::$parameterHandler->setParam("route_filter", implode(",", $route_filter), true);
      }
      $rows = "";
      foreach ($back as $row) {
        $rows .= Eisodos::$templateEngine->getTemplate($this->templateFolder . "filter.row",
          array("id" => $row["id"],
            "name" => $row["name"],
            "checked" => in_array($row["id"], $route_filter, false) ? "checked" : ""),
          false);
      }
      $responseArray['html'] = Eisodos::$templateEngine->getTemplate($this->templateFolder . "filter.main", array("ROWS" => $rows), false);
      header('Content-type: application/json');
      Eisodos::$templateEngine->addToResponse(json_encode($responseArray, JSON_THROW_ON_ERROR));
      Eisodos::$render->finish();
      exit;
    }
    
    
  }