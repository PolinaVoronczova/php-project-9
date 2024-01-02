### Hexlet tests and linter status:
[![Actions Status](https://github.com/PolinaVoronczova/php-project-9/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/PolinaVoronczova/php-project-9/actions)
[![Github Actions](https://github.com/PolinaVoronczova/php-project-9/actions/workflows/lint-check.yml/badge.svg)](https://github.com/PolinaVoronczova/php-project-9/actions)
[![Maintainability](https://api.codeclimate.com/v1/badges/18ad868f6b70d31dc278/maintainability)](https://codeclimate.com/github/PolinaVoronczova/php-project-9/maintainability)
## Page Analyzer
Page Analyzer is a website that analyzes the specified pages for SEO suitability, similar to PageSpeed Insights
## Minimum requirements
* PHP 7
* Compouser 2.2.4
## Installation
    make install
## Connect database and create table
    export DATABASE_URL=postgresql://janedoe:mypassword@localhost:5432/mydb
    psql -a -d $DATABASE_URL -f database.sql
## Demo
[https://php-page-analyzer.onrender.com/](https://php-page-analyzer.onrender.com/)