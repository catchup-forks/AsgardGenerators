<?php

return [
  'name' => 'Asgardgenerators',

    // migration
  'migration' => [
    'template' => base_path('Modules/Asgardgenerators/templates').DIRECTORY_SEPARATOR.'migration.txt',
    'output_path' => database_path('migrations'),

  ],
    // models
  'models' => [
    'template' => base_path('Modules/Asgardgenerators/templates').DIRECTORY_SEPARATOR.'model.txt',
    'output_path' => app_path('Models'),
  ],
    // repositories
  'repositories' => [
    'template' => base_path('Modules/Asgardgenerators/templates'),
    'bindings_template' => base_path('Modules/Asgardgenerators/templates').DIRECTORY_SEPARATOR.'bindings.txt',
    'output_path' => app_path('Repositories'),
  ],
    // views
  'views' => [
    'template' => base_path('Modules/Asgardgenerators/templates/views'),
    'output_path' => base_path('resource/views'),
    'base_template_name' => 'app',
  ],
    // controllers
  'controllers' => [
    'template' => base_path('Modules/Asgardgenerators/templates').DIRECTORY_SEPARATOR.'admin-controller.txt',
    'route_template' => base_path('Modules/Asgardgenerators/templates').DIRECTORY_SEPARATOR.'route-resource.txt',
    'output_path' => app_path('Http/Controllers'),
  ],

];
