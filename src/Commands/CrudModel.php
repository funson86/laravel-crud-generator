<?php

namespace Funson86\LaravelCrudGenerator\Commands;

use App\User;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\DB;

class CrudModel extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crud-model {name} {--table=} {--prefix=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create model based on table';

    // vars for generate
    private $table;
    private $primaryKey;
    private $timestamps = [];
    private $fillable = [];
    private $attribute = [];

    const TYPE_PK = 'pk';
    const TYPE_BIGPK = 'bigpk';
    const TYPE_STRING = 'string';
    const TYPE_TEXT = 'text';
    const TYPE_SMALLINT = 'smallint';
    const TYPE_INTEGER = 'integer';
    const TYPE_BIGINT = 'bigint';
    const TYPE_FLOAT = 'float';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_DATETIME = 'datetime';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_TIME = 'time';
    const TYPE_DATE = 'date';
    const TYPE_BINARY = 'binary';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_MONEY = 'money';
    public $typeMap = [
        'tinyint' => self::TYPE_SMALLINT,
        'bit' => self::TYPE_INTEGER,
        'smallint' => self::TYPE_SMALLINT,
        'mediumint' => self::TYPE_INTEGER,
        'int' => self::TYPE_INTEGER,
        'integer' => self::TYPE_INTEGER,
        'bigint' => self::TYPE_BIGINT,
        'float' => self::TYPE_FLOAT,
        'double' => self::TYPE_FLOAT,
        'real' => self::TYPE_FLOAT,
        'decimal' => self::TYPE_DECIMAL,
        'numeric' => self::TYPE_DECIMAL,
        'tinytext' => self::TYPE_TEXT,
        'mediumtext' => self::TYPE_TEXT,
        'longtext' => self::TYPE_TEXT,
        'longblob' => self::TYPE_BINARY,
        'blob' => self::TYPE_BINARY,
        'text' => self::TYPE_TEXT,
        'varchar' => self::TYPE_STRING,
        'string' => self::TYPE_STRING,
        'char' => self::TYPE_STRING,
        'datetime' => self::TYPE_DATETIME,
        'year' => self::TYPE_DATE,
        'date' => self::TYPE_DATE,
        'time' => self::TYPE_TIME,
        'timestamp' => self::TYPE_TIMESTAMP,
        'enum' => self::TYPE_STRING,
    ];

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return config('laravel-crud-generator.custom_template')
            ? config('laravel-crud-generator.path') . '/model.stub'
            : __DIR__ . '/../stubs/model.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return config('laravel-crud-generator.namespace_model')
            ? config('laravel-crud-generator.namespace_model')
            : $rootNamespace;
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get($this->getStub());

        $table = $this->option('table');
        $prefix = $this->option('prefix');
        if (!$name || !$table) {
            $this->comment('Command: crud-model {name} {--table=} {--prefix=}');
            exit;
        }

        return $this->replaceNamespace($stub, $name)
            ->compile($stub, $table, $prefix)
            ->generate($stub)
            ->replaceClass($stub, $name);
    }

    /**
     * Replace the table for the given stub.
     *
     * @param  string  $stub
     * @param  string  $table
     *
     * @return $this
     */
    protected function compile($stub, $table, $prefix)
    {
        if (strlen($prefix) > 0) {
            $this->table = str_replace($prefix, '', $table);
        } else {
            $this->table = $table;
        }

        $result = DB::select('desc ' . $table);
        foreach ($result as $item) {
            // primary key doesn't need to be generate to fillable
            if ($item->Key == 'PRI') {
                $this->primaryKey = $item->Field;
            } else {
                // 含有create or update 不用到fillable
                if (strpos($item->Field, 'create') !== false) {
                    $this->timestamps['created_at'] = $item->Field;
                } elseif (strpos($item->Field, 'update') !== false) {
                    $this->timestamps['updated_at'] = $item->Field;
                } else {
                    array_push($this->fillable, $item->Field);
                }
            }
            $type = self::TYPE_STRING;
            if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $item->Type, $matches)) {
                $key = strtolower($matches[1]);
                if (isset($this->typeMap[$key])) {
                    $type = $this->typeMap[$key];
                }
            }
            array_push($this->attribute, ['field' => $item->Field, 'type' => $this->getPhpType($type)]);
        }

        return $this;
    }

    protected function generate(&$stub)
    {
        if ($this->table)
            $stub = str_replace('{{table}}', $this->table, $stub);

        if ($this->primaryKey)
            $stub = str_replace('{{primaryKey}}', $this->primaryKey, $stub);

        if (!empty($this->timestamps)
            && isset($this->timestamps['created_at'])
            && isset($this->timestamps['updated_at'])) {
            $timestamps = <<<EOD
/**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = '{$this->timestamps['created_at']}';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = '{$this->timestamps['updated_at']}';
EOD;
        } else {
            $timestamps = <<<EOD
/**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;
EOD;
        }
        $stub = str_replace('{{timestamps}}', $timestamps, $stub);

        // fillable
        if (!empty($this->fillable)) {
            $fillable = '';
            foreach ($this->fillable as $item) {
                if ($fillable == '') {
                    $fillable .= <<<EOD
'$item',
EOD;
                } else {
                    $fillable .= <<<EOD

        '$item',
EOD;
                }
            }
        }
        $stub = str_replace('{{fillable}}', $fillable, $stub);

        // attribute
        if (!empty($this->attribute)) {
            $attribute = '';
            $comment = '';
            foreach ($this->attribute as $item) {
                $words = '';
                $arrWords = explode('_', $item['field']);
                foreach ($arrWords as $word) {
                    if ($words == '')
                        $words .= ucfirst($word);
                    else
                        $words .= ' ' . ucfirst($word);
                }
                if ($attribute == '') {
                    $attribute .= <<<EOD
'{$item['field']}' => '$words',
EOD;
                } else {
                    $attribute .= <<<EOD

            '{$item['field']}' => '$words',
EOD;
                }

                if ($comment == '') {
                    $comment .= <<<EOD
@property {$item['type']} \${$item['field']}
EOD;
                } else {
                    $comment .= <<<EOD

 * @property {$item['type']} \${$item['field']}
EOD;
                }
            }
        }
        $stub = str_replace('{{attribute}}', $attribute, $stub);
        $stub = str_replace('{{comment}}', $comment, $stub);

        return $this;
    }

    /**
     * Extracts the PHP type from abstract DB type.
     * @param ColumnSchema $column the column schema information
     * @return string PHP type name
     */
    protected function getPhpType($type)
    {
        static $typeMap = [
            // abstract type => php type
            'smallint' => 'integer',
            'integer' => 'integer',
            'bigint' => 'integer',
            'boolean' => 'boolean',
            'float' => 'double',
            'binary' => 'resource',
        ];
        if (isset($typeMap[$type])) {
            return $typeMap[$type];
        } else {
            return 'string';
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    /*public function handle()
    {
        $name = $this->argument('name');
        $table = $this->option('table');
        $prefix = $this->option('prefix');
        $this->comment($name);
        $this->comment($table);
        $this->comment($prefix);
        if (!$name || !$table) {
            $this->comment('Command: crud-model {name} {--table=} {--prefix=}');
            exit;
        }

        $result = DB::select('desc ' . $table);
        foreach ($result as $item) {
            if($item->Key == 'PRI') {
                echo $item->Field;
            }
        }
    }*/
}
