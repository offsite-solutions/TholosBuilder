<?php
  
  /**
   * Callback functions used in Tholos Builder templates
   */
   
  namespace TholosBuilder;
  
  use Eisodos\Eisodos;
  
  class TholosBuilderCallback {
    public static function _eq($params = array(), $parameterPrefix = ''): string {
      if (Eisodos::$parameterHandler->eq($params['param'], $params['value'])) {
        return Eisodos::$templateEngine->getTemplate($params['true'], array(), false);
      }
      
      return Eisodos::$templateEngine->getTemplate($params['false'], array(), false);
    }
    
    public static function _eqs($params = array(), $parameterPrefix = ''): string {
      return Eisodos::$parameterHandler->eq($params['param'], $params['value'], Eisodos::$utils->safe_array_value($params, 'defaultvalue')) ? $params['true'] : $params['false'];
    }
    
    public static function _neq($params = array(), $parameterPrefix = ''): string {
      if (Eisodos::$parameterHandler->neq($params['param'], $params['value'])) {
        return Eisodos::$templateEngine->getTemplate($params['true'], array(), false);
      }
      
      return Eisodos::$templateEngine->getTemplate($params['false'], array(), false);
    }
    
    public static function _neqs($params = array(), $parameterPrefix = ''): string {
      return Eisodos::$parameterHandler->neq($params['param'], $params['value']) ? $params['true'] : $params['false'];
    }
    
    public static function _case($params = array(), $parameterPrefix = ''): string {
      return Eisodos::$templateEngine->getTemplate(
        Eisodos::$utils->safe_array_value(
          $params,
          Eisodos::$parameterHandler->getParam($params['param']),
          Eisodos::$utils->safe_array_value($params, 'else')),
        array(),
        false);
    }
    
    public static function _cases($params = array(), $parameterPrefix = ''): string {
      return Eisodos::$utils->safe_array_value($params, Eisodos::$parameterHandler->getParam($params['param']), Eisodos::$utils->safe_array_value($params, 'else'));
    }
    
    public static function _trim($params = array(), $parameterPrefix = ''): string {
      return trim(Eisodos::$templateEngine->replaceParamInString($params['value']));
    }
    
    public static function _param2($params = array(), $parameterPrefix = ''): string {
      if (!$params['param']) {
        return '';
      }
      
      return Eisodos::$templateEngine->replaceParamInString(Eisodos::$parameterHandler->getParam(Eisodos::$parameterHandler->getParam($params['param'])));
    }
    
    public static function _listToOptions($params = array(), $parameterPrefix = ''): string {
      $result = '';
      foreach (explode($params['separator'], Eisodos::$parameterHandler->getParam($params['options'])) as $item) {
        $result .= '<option value="' . $item . '" ' . (Eisodos::$parameterHandler->eq($params['selected'], $item) ? 'selected' : '') . '>' . $item . '</option>';
      }
      
      return $result;
    }
    
  }