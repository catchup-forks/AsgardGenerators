# Asgard Generators

Asgard generators scaffold CRUD (Create/Read/Update/Delete) structure inside a given Asgard Module with the database as 
template.

Items being generated
  - Eloquent Models (Entities)
  - Repositories
  - Controllers
  - Backend Routes
  - Backend views

### Version
dev

### Installation

Clone this repo in the Modules directory of your AsgardCMS project.

#### Add the dependencies

Add the following to composer.json

```
"require-dev": {
    "xethron/migrations-generator": "dev-l5",
    "way/generators": "dev-feature/laravel-five-stable",
    "user11001/eloquent-model-generator": "~2.0"
}
```

You also need to point to the fork of the way/generators repo. See [Xethron/migrations-generator](https://github.com/Xethron/migrations-generator) for more info about this.

```
"repositories": [
    {
        "type": "git",
        "url": "git@github.com:jamisonvalenta/Laravel-4-Generators.git"
    }
]
```

Next, run `composer update`

### Todos

 - Add tests
 - Add translations
 - Add relationship field generation

License
----

MIT

**Free Software, Hell Yeah!**
