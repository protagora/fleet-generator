<?php

class Formation
{
    
    const MILITARY_MIN_SHIPS = 3;
    const MILITARY_MAX_SHIPS = 99;
    const CIVILIAN_MIN_SHIPS = 2;
    const CIVILIAN_MAX_SHIPS = 99;
    const JUNIOR_RATIO = 30;
    const JUNIOR_MIN = 0;
    const JUNIOR_MAX = 65;
    
    const TYPE_ATTACK = 1;
    const TYPE_ESCORT = 2;
    
    const DEFAULT_FORMATION = "A";
    
    protected $_formationTypes = [
        'A', 'a', 'Attack', 'E', 'e', 'Escort'
    ];
    
    protected $_shipClasses = [
        'military' => [
            Ship::CLASS_DREADNOUGHT,
            Ship::CLASS_INTERCEPTOR,
            Ship::CLASS_LEVIATHAN,
        ],
        'civilian' => [
            Ship::CLASS_TRANSPORT,
            Ship::CLASS_RECREATION,
        ]
    ];
    
    /** @var Config */
    protected $_config;
    protected $_militaryShips;
    protected $_civilianShips;
    protected $_i;


    // singleton
    protected static $_instance = null;
    
    public static final function instance()
    {
        if (! self::$_instance instanceof self)
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }
    
    protected function __construct()
    {
        
    }
    
    protected function __clone()
    {
        trigger_error("Clone is not allowed", E_ERROR);
    }
    
    protected function __wakeup()
    {
        trigger_error("Wakeup is not allowed");
    }
    
    public function getFormation(Config $config)
    {
        
        $this->_processConfig($config);
        
        $this->_militaryShips = [];
        $this->_civilianShips = [];
        $this->_i = 0;
        
        $this->_createMilitaryShips();
        $this->_createCivilianShips();
        
        return $this->_getFormation();
        
    }
    
    public function outputFormation(array $formation)
    {
        
        $method = '_output' . $this->_config->getType();
        if (method_exists($this, $method)) 
        {
            $this->$method($formation);
        }
        else 
        {
            throw new \Exception("Output method '" . (string) $this->_config->getType() . "' not implemented");
        }
        
        return $this;
        
    }
    
    protected function _outputAttack($formation)
    {
        $buffer = [];
        $break = \ceil(\count($this->_militaryShips) / 2);
        
        foreach ($this->_militaryShips as $ship)
        {
            if ($break == 0)
            {
                echo \implode (", ", $buffer);
                echo "\n";
                $buffer = [];
            }
            $tmp = $ship->getCallSign() . " " . $ship->getUnique() . "[" . $ship->getStrength() . "]";
            if ($ship->getJunior())
            {
                $tmp .= " Junior";
            }
            $buffer[] = $tmp;
            $break--;
        }
        
        echo \implode(", ", $buffer);
        echo "\n";
        
        $buffer = [];
        foreach ($this->_civilianShips as $ship)
        {
            $tmp = $ship->getCallSign() . " " . $ship->getUnique() . "[" . $ship->getStrength() . "]";
            if ($ship->getJunior())
            {
                $tmp .= " Junior";
            }
            $buffer[] = $tmp;
        }
        
        echo implode(", ", $buffer) . ".\n";
        
    }
    
    protected function _outputEscort($formation)
    {
        $buffer = [];
        $current = \reset($formation)->getType();
        
        foreach ($formation as $ship)
        {
            if ($ship->getType() != $current)
            {
                $current = $ship->getType();
                echo \implode(", ", $buffer);
                $buffer = [];
                echo "\n";
            }
            $tmp = $ship->getCallSign() . " " . $ship->getUnique() . "[" . $ship->getStrength() . "]";
            if ($ship->getJunior())
            {
                $tmp .= " Junior";
            }
            $buffer[] = $tmp;
        }
        
        echo \implode(", ", $buffer);
    }
    
    protected function _getFormation()
    {
        
        $method = '_formation' . $this->_config->getType();
        if (method_exists($this, $method))
        {
            return $this->$method();
        }
        else {
            throw new \Exception("Unsupported formation mode");
        }
        
    }
    
    protected function _formationAttack()
    {
        return \array_merge($this->_militaryShips, $this->_civilianShips);
    }
    
    protected function _formationEscort()
    {
        $return = [];
        $front = true;
        
        foreach (\array_merge($this->_civilianShips, $this->_militaryShips) as $ship)
        {
            $front = !$front;
            if ($front)
            {
                \array_unshift($return, $ship);
            }
            else
            {
                \array_push($return, $ship);
            }
        }
        
        return $return;
    }
    
    protected function _processConfig(Config $config)
    {
        
        $this->_config = $config;
        
        in_array($this->_config->getType(), $this->_formationTypes) ?: $this->_config->setType(self::DEFAULT_FORMATION);
        if (in_array($this->_config->getType(), \array_slice($this->_formationTypes, 0, 3)))
        {
            $this->_config->setType('Attack');
        }
        else
        {
            $this->_config->setType('Escort');
        }
        $this->_config->getTotalCivilianShips() >= self::CIVILIAN_MIN_SHIPS ?: $this->_config->setTotalCivilianShips(self::CIVILIAN_MIN_SHIPS);
        $this->_config->getTotalCivilianShips() <= self::CIVILIAN_MAX_SHIPS ?: $this->_config->setTotalCivilianShips(self::CIVILIAN_MAX_SHIPS);
        $this->_config->getTotalMilitaryShips() >= self::MILITARY_MIN_SHIPS ?: $this->_config->setTotalMilitaryShips(self::MILITARY_MIN_SHIPS);
        $this->_config->getTotalMilitaryShips() <= self::MILITARY_MAX_SHIPS ?: $this->_config->setTotalMilitaryShips(self::MILITARY_MAX_SHIPS);
        
        if (!is_int($this->_config->getJuniorPercent()))
        {
            $this->_config->setJuniorPercent(self::JUNIOR_RATIO);
        }
        else {
            $this->_config->getJuniorPercent() >= self::JUNIOR_MIN ?: $this->_config->setJuniorPercent(self::JUNIOR_MIN);
            $this->_config->getJuniorPercent() <= self::JUNIOR_MAX ?: $this->_config->setJuniorPercent(self::JUNIOR_MAX);
        }
        // strenghts ignored atm
        
    }
    
    protected function _createMilitaryShips()
    {
        $minimum = $this->_config->getTotalMilitaryShips() * ($this->_config->getJuniorPercent() / 100);
        
        for ($i = 0; $i < $this->_config->getTotalMilitaryShips(); $i++)
        {
            $this->_i++;
            $class = \rand(0, \count($this->_shipClasses['military']) - 1);
            $junior = ($this->_getExperience($this->_config->getTotalMilitaryShips()) < $minimum);
            $this->_militaryShips[] = new Ship($this->_shipClasses['military'][$class], $this->_i, $junior);
        }
        
        \usort($this->_militaryShips, function($a, $b) { return $a->getStrength() < $b->getStrength(); });
    }
    
    protected function _createCivilianShips()
    {
        $minimum = $this->_config->getTotalCivilianShips() * ($this->_config->getJuniorPercent() / 100);
        
        for ($i = 0; $i < $this->_config->getTotalCivilianShips(); $i++)
        {
            $this->_i++;
            $class = \rand(0, \count($this->_shipClasses['civilian']) - 1);
            $junior = ($this->_getExperience($this->_config->getTotalCivilianShips()) < $minimum);
            $this->_civilianShips[] = new Ship($this->_shipClasses['civilian'][$class], $this->_i, $junior);
        }
        
        \usort($this->_civilianShips, function($a, $b) { return $a->getStrength() < $b->getStrength(); });
    }
    
    protected function _getExperience($total)
    {
        return \rand(0, $total);
    }
    
}

class Config
{
    
    protected $_type = null;
    protected $_totalCivilianShips = null;
    protected $_totalMilitaryShips = null;
    protected $_juniorPercent = null;
    protected $_strengths = [];
    
    public function __construct($data = [])
    {
        $this->setType($data['type']);
        $this->setTotalCivilianShips($data['civilian']);
        $this->setTotalMilitaryShips($data['military']);
        $this->setJuniorPercent($data['junior']);
    }
    
    public function getType()
    {
        return $this->_type;
    }
    
    public function setType($type = null)
    {
        $this->_type = $type;
        return $this;
    }
    
    public function getTotalCivilianShips()
    {
        return $this->_totalCivilianShips;
    }
    
    public function setTotalCivilianShips($ships = null)
    {
        $this->_totalCivilianShips = (int) $ships;
        return $this;
    }
    
    public function getTotalMilitaryShips()
    {
        return $this->_totalMilitaryShips;
    }
    
    public function setTotalMilitaryShips($ships = null)
    {
        $this->_totalMilitaryShips = (int) $ships;
        return $this;
    }
    
    public function getJuniorPercent()
    {
        return $this->_juniorPercent;
    }
    
    public function setJuniorPercent($junior = null)
    {
        $this->_juniorPercent = $junior;
        return $this;
    }
    
    public function getStrenghts()
    {
        return $this->_strengths;
    }
    
    public function setStrenghts($strengths = [])
    {
        $this->_strengths = $strengths;
        return $this;
    }
    
}

class Ship
{
    const SHIP_TYPE_MILITARY = 1;
    const SHIP_TYPE_CIVILIAN = 2;
    const SHIP_TYPE_MILITARY_TITLE = 'Military Ship';
    const SHIP_TYPE_CIVILIAN_TITLE = 'Civilian Ship';
    
    const CLASS_DREADNOUGHT = 1;
    const CLASS_INTERCEPTOR = 2;
    const CLASS_LEVIATHAN = 3;
    const CLASS_TRANSPORT = 4;
    const CLASS_RECREATION = 5;
    
    const SS_DREADNOUGHT_MAX = 499;
    const SS_DREADNOUGHT_MIN = 300;
    const SS_INTERCEPTOR_MAX = 299;
    const SS_INTERCEPTOR_MIN = 200;
    const SS_LEVIATHAN_MAX = 199;
    const SS_LEVIATHAN_MIN = 100;
    const SS_TRANSPORT_MAX = 49;
    const SS_TRANSPORT_MIN = 30;
    const SS_RECREATION_MAX = 19;
    const SS_RECREATION_MIN = 10;
    
    protected $_type = null;
    protected $_class = null;
    protected $_callSign = null;
    protected $_strength = null;
    protected $_unique = null;
    
    protected $_types = [
        self::SHIP_TYPE_MILITARY => [
            'type' => self::SHIP_TYPE_MILITARY_TITLE,
        ],
        self::SHIP_TYPE_CIVILIAN => [
            'type' => self::SHIP_TYPE_CIVILIAN_TITLE,
        ],
    ];
    
    protected $_classes = [
        self::CLASS_DREADNOUGHT => [
            'type' => self::SHIP_TYPE_MILITARY,
            'title' => 'Dreadnought',
            'strength' => [
                'max' => self::SS_DREADNOUGHT_MAX,
                'min' => self::SS_DREADNOUGHT_MIN,
            ],
            'name' => null,
            'unique' => null,
        ],
        self::CLASS_INTERCEPTOR => [
            'type' => self::SHIP_TYPE_MILITARY,
            'title' => 'Interceptor',
            'strength' => [
                'max' => self::SS_INTERCEPTOR_MAX,
                'min' => self::SS_INTERCEPTOR_MIN,
            ],
            'name' => null,
            'unique' => null,
        ],
        self::CLASS_LEVIATHAN => [
            'type' => self::SHIP_TYPE_MILITARY,
            'title' => 'Leviathan',
            'strength' => [
                'max' => self::SS_LEVIATHAN_MAX,
                'min' => self::SS_LEVIATHAN_MIN,
            ],
            'name' => null,
            'unique' => null,
        ],
        self::CLASS_TRANSPORT => [
            'type' => self::SHIP_TYPE_CIVILIAN,
            'title' => 'Transport',
            'strength' => [
                'max' => self::SS_TRANSPORT_MAX,
                'min' => self::SS_TRANSPORT_MIN,
            ],
            'name' => null,
            'unique' => null,
        ],
        self::CLASS_RECREATION => [
            'type' => self::SHIP_TYPE_CIVILIAN,
            'title' => 'Recreation',
            'strength' => [
                'max' => self::SS_RECREATION_MAX,
                'min' => self::SS_RECREATION_MIN,
            ],
            'name' => null,
            'unique' => null,
        ],
    ];
    
    public function __construct($class, $unique = null, $junior = false)
    {
        $struct = $this->_classes[$class];
        
        $this->_callSign = $struct['title'];
        $this->_class = $class;
        $this->_type = $struct['type'];
        $this->_unique = $unique ?: 0;
        $this->_strength = \rand($struct['strength']['min'], $struct['strength']['max']);
        $this->_junior = $junior;
    }
    
    public function getStrength()
    {
        return $this->_strength;
    }
    
    public function getType()
    {
        return $this->_type;
    }
    
    public function getClass()
    {
        return $this->_class;
    }
    
    public function getCallSign()
    {
        return $this->_callSign;
    }
    
    public function getUnique()
    {
        return $this->_unique;
    }
    
    public function getJunior()
    {
        return !!$this->_junior;
    }
    
}

class Console
{
    
    protected $_continue = true;
    protected $_input = [];
    protected $_config = null;
    protected $_formation = null;
    
    // singleton
    protected static $_instance = null;
    
    public static final function instance()
    {
        if (! self::$_instance instanceof self)
        {
            self::$_instance = new self;
        }
        return self::$_instance;
    }
    
    protected function __construct()
    {
        
    }
    
    protected function __clone()
    {
        trigger_error("Clone is not allowed", E_ERROR);
    }
    
    protected function __wakeup()
    {
        trigger_error("Wakeup is not allowed");
    }
    
    // the meat
    public function run()
    {
        // printout the welcome message
        $this->_welcomeMessage();
        
        // infinite loop for user input
        while(1)
        {
            // should we proceed to fleet formation
            $endOfInput = $this->_collectUserInput();
            
            
            if (true !== $endOfInput)
            {
                // yea, go for it
                $this->_dispatch();
            }
            else
            {
                // no, we are done
                break;
            }
        }
        
        // let's clean up
        $this->_cleanUp();
    }
    
    protected function _welcomeMessage()
    {
        // here we printout message to the user, containing instructions
        echo "\n\n ==================================";
        echo "\n\nHello! Welcome to Fleet Formation Utility." .
             "\n\nPlease read instructions and offered options carefully" .
             "\nand respond to them appropriately.";
        echo "\nAny invalid options will be ignored." .
             "\n\nWhen entering selection, look for uppercase letters for shorhand" .
             "\nentry format.\n";
        echo "\nExample: Attack option could be selected" .
             "\nby typing 'Attack', 'A' or 'a'\n";
    }
    
    protected function _collectUserInput()
    {
        // now we are tapping into the input stream
        $handle = \fopen ("php://stdin","r");
        
        // and asking the user for input
        echo "\n\nBuild fleet formation (Yes, No) [Y]:\n";
        $this->_continue = \trim(\fgets($handle));
        
        if (!in_array($this->_continue, ["", "Yes", "yes", "Y", "y"]))
        {
            // non of above, don't proceed to fleet formation
            return true;
        }
        
        // this could be implemented so it insists on correct input
        
        echo "\n\nPlease enter formation type (Attack, Escort) [" . Formation::DEFAULT_FORMATION . "]:\n";
        $this->_input['type'] = \trim(\fgets($handle));
        
        echo "\n\nPlease enter enter number of military ships (" . Formation::MILITARY_MIN_SHIPS . " - " . Formation::MILITARY_MAX_SHIPS . ") [" . Formation::MILITARY_MIN_SHIPS . "]:\n";
        $this->_input['military'] = \trim(\fgets($handle));
        
        echo "\n\nPlease enter enter number of civilian ships (" . Formation::CIVILIAN_MIN_SHIPS . " - " . Formation::CIVILIAN_MAX_SHIPS . ") [" . Formation::CIVILIAN_MIN_SHIPS . "]:\n";
        $this->_input['civilian'] = \trim(\fgets($handle));
        
        echo "\n\nPlease enter enter number of Junior ships ratio (" . Formation::JUNIOR_MIN . "% - " . Formation::JUNIOR_MAX . "%) [" . Formation::JUNIOR_RATIO . "]:\n";
        $this->_input['junior'] = \trim(\fgets($handle));
        
        // @todo: ship strenghts input
        
        // all done, close the resource
        \fclose($handle);
        
        // all good, continue to fleet formation
        return false;
        
    }
    
    protected function _dispatch()
    {
        
        // create configuration from user input data
        $this->_config = new Config($this->_input);
        
        // build formation from configuration
        $this->_formation = Formation::instance()->getFormation($this->_config);
        
        // output the formation
        $this->_outputFormation();
        
    }
    
    protected function _outputFormation()
    {
        
        Formation::instance()->outputFormation($this->_formation);
        
    }
    
    protected function _cleanUp()
    {
        
        // do any neccessary cleanup and greet the user
        echo "\nBye ;)\n\n";
        
    }
    
}

Console::instance()->run();
