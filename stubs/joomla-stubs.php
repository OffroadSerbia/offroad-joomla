<?php
// Minimal stubs for PHPStan to understand Joomla symbols used in plugins.
// This file is included only by PHPStan (phpstan.neon: stubFiles).

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
            return null;
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
            return new \stdClass();
        }
        public function getPathway()
        {
            return new \stdClass();
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
                    return $n;
                }
                public function quote($n)
                {
                    return "'" . $n . "'";
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
            return $a;
        }
    }
}

namespace Joomla\CMS\HTML {
    class HTMLHelper
    {
        public static function _() {}
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
        public function setTimezone(\DateTimeZone $timezone): \DateTime
        {
            return parent::setTimezone($timezone);
        }
    }
}
