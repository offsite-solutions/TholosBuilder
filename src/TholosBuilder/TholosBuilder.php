<?php
  /** @noinspection DuplicatedCode */
  /** @noinspection SpellCheckingInspection */
  /** @noinspection PhpUnusedFunctionInspection */
  /** @noinspection NotOptimalIfConditionsInspection */
  
  namespace TholosBuilder;
  
  use Eisodos\Abstracts\Singleton;
  
  /**
   * TholosBuilder Bootstrap class
   *
   * This class provides bootstrapping functionality for Tholos. It registers and implements autoload function for
   * loading all Tholos components automatically.
   *
   * @package TholosBuilder
   * @see TholosBuilderApplication
   */
  class TholosBuilder extends Singleton {
    
    /**
     * @var TholosBuilderApplication Reference to TholosBuilderApplication for quick component access.
     */
    public static TholosBuilderApplication $app;
    
    /**
     * Tholos class prefix used by the autoloader for detecting Tholos-related class load requests
     */
    public const string THOLOSBUILDER_CLASS_PREFIX = "TholosBuilder\\";
    
    public function init(array $options_): TholosBuilder {
      self::$app = TholosBuilderApplication::getInstance();
      
      self::$app->init([]);
      
      return $this;
    }
  }