# wwoof.de better UI

The program can be used to import and visualize public data from the website wwoof.de.

## Pre-installation

You will need the following program installed in your computer:

- [composer](https://getcomposer.org/download/)


## Installation

Setup your environment:
```
cp .env.example .env
```
Change the values you need in the `.env` file.
Start the server:
```
php -S localhost:8000 -t web
```
Try it! [http://localhost:8000](http://localhost:8000)

## Configuration

When you are deploying, there are a few configuration you might want to change. Here are the available configurations:

**ENVIRONMENT**: Can be _development_, _staging_, _testing_ or _production_ (default: _development_)

**BASEURL**: If you are running the program in a subdirectory, you can change this. It will make the routes and the assets work.

## Import

Create the database shema
```
php bin/console app:create:schema
```

Import the data
```
php bin/console app:import
``
