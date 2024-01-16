# Webform OpenFisca Integration

## Overview

This module integrates Drupal Webform functionality with the OpenFisca API, allowing for seamless communication and calculation based on OpenFisca rules.

## Features
  - Handles communication with the OpenFisca API through Guzzle HTTP client.
  - Provides methods to post data, retrieve variables, parameters, and calculations from the OpenFisca API.
  - Webform handler for processing submissions and interacting with OpenFisca API.
  - Provides a mechanism for creating redirect rules based on OpenFisca responses.

## Installation

1. Install the module as you would any other Drupal module.

## Usage

1. Ensure the OpenFisca API configuration is correctly set in the Drupal configuration.
2. Configure Webform fields to map to OpenFisca variables and set any specific rules.
3. Submit a Webform, and the module will communicate with OpenFisca API, perform calculations, and handle redirects based on configured rules.

## Configuration

### Webform Fields Configuration

- Map Webform fields to OpenFisca variables through the Webform UI.
- Configure the OpenFisca API endpoint and other settings.

### Redirect Rules Configuration

- Define redirect rules in Drupal content type "rac" associated with Webforms.
- Rules are evaluated based on OpenFisca calculation results, and matching rules trigger redirects.
