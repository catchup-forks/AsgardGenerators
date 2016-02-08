<?php namespace Modules\Asgardgenerators\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class GenerateStructureCommand extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'bitsoflove:structure';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$config = app()->make('config');

        var_dump($config->get('database.default'));

	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return [
			['tables', InputArgument::OPTIONAL, 'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments'],
		];
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
//		['connection', 'c', InputOption::VALUE_OPTIONAL, 'The database connection to use.', $this->config->get( 'database.default' )],
//		['tables', 't', InputOption::VALUE_OPTIONAL, 'A list of Tables you wish to Generate Migrations for separated by a comma: users,posts,comments'],
//		['ignore', 'i', InputOption::VALUE_OPTIONAL, 'A list of Tables you wish to ignore, separated by a comma: users,posts,comments' ],
//		['path', 'p', InputOption::VALUE_OPTIONAL, 'Where should the file be created?'],
//		['templatePath', 'tp', InputOption::VALUE_OPTIONAL, 'The location of the template for this generator'],
//		['defaultIndexNames', null, InputOption::VALUE_NONE, 'Don\'t use db index names for migrations'],
//		['defaultFKNames', null, InputOption::VALUE_NONE, 'Don\'t use db foreign key names for migrations'],
		];
	}

}
