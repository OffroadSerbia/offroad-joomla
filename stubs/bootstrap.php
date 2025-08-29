<?php
// Bootstrap stubs for PHPStan (runtime-aliased minimal Joomla classes)
// Purpose: satisfy symbol discovery without analyzing signatures.

namespace Joomla\Registry {
    class Registry
    {
        public function get($key, $default = null)
        {
            return $default;
        }
    }
}

namespace Joomla\CMS\Plugin {
    class CMSPlugin
    {
        public $params;
        public function __construct() {}
    }
}

namespace Joomla\CMS\Application {
    class CMSApplication
    {
        public function isClient($c)
        {
            return true;
        }
        public function getMenu($c = null)
        {
            return new class {
                public function getMenu()
                {
                    return [];
                }
                public function getActive()
                {
                    return (object)['id' => 1, 'home' => false];
                }
                public function getDefault()
                {
                    return (object)['language' => '*', 'home' => 1];
                }
            };
        }
        public function setHeader($a, $b, $c = null) {}
        public function setBody($b) {}
        public function getBody()
        {
            return '';
        }
        public function respond() {}
        public function close() {}
        public function getInput()
        {
            return new class {
                public function getCmd($k)
                {
                    return '';
                }
                public function getInt($k)
                {
                    return 0;
                }
            };
        }
        public function getPathway()
        {
            return new class {
                public function getPathway()
                {
                    return [];
                }
            };
        }
    }
}

namespace Joomla\CMS\Document {
    class HtmlDocument
    {
        public function getLanguage()
        {
            return 'en-GB';
        }
        public function addHeadLink($a, $b, $c, $d = []) {}
        public function setMetaData($a, $b, $c = null) {}
        public function getHeadData()
        {
            return ['scripts' => [], 'custom' => [], 'metaTags' => []];
        }
        public function getTitle()
        {
            return '';
        }
        public function getDescription()
        {
            return '';
        }
        public function addCustomTag($t) {}
    }
}

namespace Joomla\CMS {
    class Factory
    {
        public static function getDocument()
        {
            return new \Joomla\CMS\Document\HtmlDocument();
        }
        public static function getDbo()
        {
            return new class {
                public function getQuery($b = false)
                {
                    return new class {
                        public function select($a)
                        {
                            return $this;
                        }
                        public function from($a)
                        {
                            return $this;
                        }
                        public function where($a)
                        {
                            return $this;
                        }
                        public function order($a)
                        {
                            return $this;
                        }
                        public function setLimit($a)
                        {
                            return $this;
                        }
                        public function join($a, $b)
                        {
                            return $this;
                        }
                        public function setLimitBy($a)
                        {
                            return $this;
                        }
                    };
                }
                public function setQuery($q) {}
                public function loadObject()
                {
                    return null;
                }
                public function loadObjectList()
                {
                    return [];
                }
                public function loadColumn()
                {
                    return [];
                }
                public function loadResult()
                {
                    return null;
                }
                public function quoteName($n)
                {
                    return (string)$n;
                }
                public function quote($n)
                {
                    return "'" . (string)$n . "'";
                }
            };
        }
        public static function getUser($id)
        {
            return (object)['name' => ''];
        }
        public static function getConfig()
        {
            return new class {
                public function get($k)
                {
                    return 'UTC';
                }
            };
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static function root()
        {
            return 'https://example.com/';
        }
        public static function getInstance()
        {
            return new class {
                public function getPath()
                {
                    return '/';
                }
                public function getHost()
                {
                    return 'example.com';
                }
            };
        }
    }
}

namespace Joomla\CMS\Router {
    class Route
    {
        public static function _($a)
        {
            return (string)$a;
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        public static function _(...$args) {}
    }
}

namespace Joomla\CMS\Language {
    class LanguageHelper
    {
        public static function getLanguages($a)
        {
            return [];
        }
    }
}

namespace {
    class JLanguageAssociations
    {
        public static function isEnabled()
        {
            return false;
        }
    }
}

namespace Joomla\CMS\Association {
    class AssociationHelper
    {
        public static function getAssociations($a, $b, $c, $d)
        {
            return [];
        }
    }
}

namespace Joomla\CMS\Date {
    class Date extends \DateTime
    {
        public function __construct($t = 'now', $tz = null)
        {
            parent::__construct(is_string($t) ? $t : 'now', $tz ?: new \DateTimeZone('UTC'));
        }
    }
}
