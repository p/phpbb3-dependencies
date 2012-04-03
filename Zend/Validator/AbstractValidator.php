<?php
 namespace Zend\Validator; use Zend\Translator; abstract class AbstractValidator implements Validator { protected $_value; protected $_messageVariables = array(); protected $_messageTemplates = array(); protected $_messages = array(); protected $_obscureValue = false; protected $_errors = array(); protected $_translator; protected static $_defaultTranslator; protected $_translatorDisabled = false; protected static $_messageLength = -1; public function getMessages() { return $this->_messages; } public function __invoke($value) { return $this->isValid($value); } public function getMessageVariables() { return array_keys($this->_messageVariables); } public function getMessageTemplates() { return $this->_messageTemplates; } public function setMessage($messageString, $messageKey = null) { if ($messageKey === null) { $keys = array_keys($this->_messageTemplates); foreach($keys as $key) { $this->setMessage($messageString, $key); } return $this; } if (!isset($this->_messageTemplates[$messageKey])) { throw new Exception\InvalidArgumentException("No message template exists for key '$messageKey'"); } $this->_messageTemplates[$messageKey] = $messageString; return $this; } public function setMessages(array $messages) { foreach ($messages as $key => $message) { $this->setMessage($message, $key); } return $this; } public function __get($property) { if ($property == 'value') { return $this->_value; } if (array_key_exists($property, $this->_messageVariables)) { return $this->{$this->_messageVariables[$property]}; } throw new Exception\InvalidArgumentException("No property exists by the name '$property'"); } protected function _createMessage($messageKey, $value) { if (!isset($this->_messageTemplates[$messageKey])) { return null; } $message = $this->_messageTemplates[$messageKey]; if (null !== ($translator = $this->getTranslator())) { if ($translator->isTranslated($messageKey)) { $message = $translator->translate($messageKey); } else { $message = $translator->translate($message); } } if (is_object($value)) { if (!in_array('__toString', get_class_methods($value))) { $value = get_class($value) . ' object'; } else { $value = $value->__toString(); } } else { $value = (string)$value; } if ($this->getObscureValue()) { $value = str_repeat('*', strlen($value)); } $message = str_replace('%value%', (string) $value, $message); foreach ($this->_messageVariables as $ident => $property) { $message = str_replace("%$ident%", (string) $this->$property, $message); } $length = self::getMessageLength(); if (($length > -1) && (strlen($message) > $length)) { $message = substr($message, 0, (self::getMessageLength() - 3)) . '...'; } return $message; } protected function _error($messageKey, $value = null) { if ($messageKey === null) { $keys = array_keys($this->_messageTemplates); $messageKey = current($keys); } if ($value === null) { $value = $this->_value; } $this->_errors[] = $messageKey; $this->_messages[$messageKey] = $this->_createMessage($messageKey, $value); } protected function _setValue($value) { $this->_value = $value; $this->_messages = array(); $this->_errors = array(); } public function getErrors() { return $this->_errors; } public function setObscureValue($flag) { $this->_obscureValue = (bool) $flag; return $this; } public function getObscureValue() { return $this->_obscureValue; } public function setTranslator($translator = null) { if ((null === $translator) || ($translator instanceof Translator\Adapter)) { $this->_translator = $translator; } elseif ($translator instanceof Translator\Translator) { $this->_translator = $translator->getAdapter(); } else { throw new Exception\InvalidArgumentException('Invalid translator specified'); } return $this; } public function getTranslator() { if ($this->translatorIsDisabled()) { return null; } if (null === $this->_translator) { return self::getDefaultTranslator(); } return $this->_translator; } public function hasTranslator() { return (bool)$this->_translator; } public static function setDefaultTranslator($translator = null) { if ((null === $translator) || ($translator instanceof Translator\Adapter)) { self::$_defaultTranslator = $translator; } elseif ($translator instanceof Translator\Translator) { self::$_defaultTranslator = $translator->getAdapter(); } else { throw new Exception\InvalidArgumentException('Invalid translator specified'); } } public static function getDefaultTranslator() { if (null === self::$_defaultTranslator) { if (\Zend\Registry::isRegistered('Zend_Translate')) { $translator = \Zend\Registry::get('Zend_Translate'); if ($translator instanceof Translator\Adapter) { return $translator; } elseif ($translator instanceof Translator\Translator) { return $translator->getAdapter(); } } } return self::$_defaultTranslator; } public static function hasDefaultTranslator() { return (bool)self::$_defaultTranslator; } public function setDisableTranslator($flag) { $this->_translatorDisabled = (bool) $flag; return $this; } public function translatorIsDisabled() { return $this->_translatorDisabled; } public static function getMessageLength() { return self::$_messageLength; } public static function setMessageLength($length = -1) { self::$_messageLength = $length; } } 