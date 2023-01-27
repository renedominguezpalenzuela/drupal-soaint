<?php

namespace Drupal\migration_tools;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migration_tools\Modifier\DomModifier;
use Drupal\migration_tools\Modifier\SourceModifierHtml;
use Drupal\migration_tools\Obtainer\Job;
use Drupal\migration_tools\SourceParser\HtmlBase;

class Operations {

  /**
   * Process Migration Tools Operations.
   *
   * @param array $migration_tools_settings
   *   Migration Tools Settings.
   * @param \Drupal\migrate\Row $row
   *   Migration Row.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public static function process(array $migration_tools_settings, $row) {
    if (!empty($migration_tools_settings)) {
      $path = '';

      foreach ($migration_tools_settings as $migration_tools_setting) {
        $source = $migration_tools_setting['source'];
        $source_type = !empty($migration_tools_setting['source_type']) ? $migration_tools_setting['source_type'] : 'none';

        switch ($source_type) {
          case 'url':
            $url = $row->getSourceProperty($source);

            // @todo Improve URL fetching.
            $handle = curl_init($url);
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);

            self::processCurlOptions($migration_tools_setting, $handle);

            $html = curl_exec($handle);
            $http_response_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);

            if (!in_array($http_response_code, [200, 301])) {
              $message = sprintf('Was unable to load %s, response code: %d', $url, $http_response_code);
              Message::make($message, [], Message::ERROR);

              throw new MigrateSkipRowException($message);
            }
            $url_pieces = parse_url($url);
            $path = ltrim($url_pieces['path'], '/');

            break;

          case 'html':
            $html = $row->getSourceProperty($source);
            break;

          case 'none':
            $html = '';
            break;

          default:
            throw new MigrateException('Invalid source_type specified');
        }

        $row->MTRedirector = new Redirects($row);

        // Perform Source Operations.
        $source_operations = $migration_tools_setting['source_operations'];
        if ($source_operations) {
          $source_modifier_html = new SourceModifierHtml($html);
          foreach ($source_operations as $source_operation) {
            $arguments = isset($source_operation['arguments']) ? $source_operation['arguments'] : [];
            HtmlBase::parseDynamicArguments($arguments, $row->getSource());
            $source_modifier_html->runModifier($source_operation['modifier'], $arguments);
          }
          $html = $source_modifier_html->getContent();
        }

        // Construct Jobs.
        $config_fields = $migration_tools_setting['fields'];

        // Perform DOM Operations.
        $dom_operations = !empty($migration_tools_setting['dom_operations']) ? $migration_tools_setting['dom_operations'] : [];

        $source_parser = new HtmlBase($path, $html, $row);

        foreach ($dom_operations as $dom_operation) {
          switch ($dom_operation['operation']) {
            case 'get_field':
              // Run Obtainer Jobs on field.
              if ($config_fields) {
                $field_found = FALSE;
                foreach ($config_fields as $field_name => $config_field) {
                  if ($field_name == $dom_operation['field']) {
                    $config_jobs = $config_field['jobs'];
                    if ($config_jobs) {
                      $job = new Job($field_name, $config_field['obtainer']);
                      foreach ($config_jobs as $config_job) {
                        $arguments = $config_job['arguments'] ? $config_job['arguments'] : [];
                        HtmlBase::parseDynamicArguments($arguments, $row->getSource());
                        $job->{$config_job['job']}($config_job['method'], $arguments);
                        $source_parser->addObtainerJob($job);
                      }
                    }
                    else {
                      throw new MigrateException(t('No jobs specified for field @field', ['@field' => $field_name]));
                    }
                    $source_parser->parse();
                    $field_found = TRUE;
                    break;
                  }
                }
                if (!$field_found) {
                  throw new MigrateException(t('Field @field not configured referenced in dom_operations', ['@field' => $dom_operation['field']]));
                }
              }
              break;

            case 'modifier':
              // Run DOM Modifier on queryPath.
              $dom_modifier = new DomModifier($source_parser->queryPath, $row);
              $arguments = $dom_operation['arguments'] ? $dom_operation['arguments'] : [];
              HtmlBase::parseDynamicArguments($arguments, $row->getSource());

              $dom_modifier->runModifier($dom_operation['modifier'], $arguments);
              break;

            default:
              throw new MigrateException(t('Invalid or empty operation @operation', ['@operation' => $dom_operation['operation']]));
          }
        }
      }
    }
  }

  /**
   * Applies curl options that were set in the migration yaml.
   *
   * @param array $migration_tools_setting
   *   An array of yaml values for this operation.
   * @param resource $handle
   *   The curl handle to set the options on.
   *
   * @throws \Drupal\migrate\MigrateException
   */
  protected static function processCurlOptions($migration_tools_setting, &$handle) {
    if (empty($migration_tools_setting['curl_options'])) {
      return;
    }
    $curl_options = $migration_tools_setting['curl_options'];
    if (!is_array($curl_options)) {
      $curl_options = [$curl_options];
    }
    foreach ($curl_options as $curl_option) {
      if (empty($curl_option['name']) || empty($curl_option['value'])) {
        throw new MigrateException("curl_options must have name (a curl_setopt() constant) and value (the option value)");
      }
      // We need RETURNTRANSFER to be true, so don't let them override it.
      if ('CURLOPT_RETURNTRANSFER' == $curl_option['name']) {
        continue;
      }
      $curlopt = constant($curl_option['name']);
      if (strpos($curl_option['name'], 'CURLOPT_') !== 0 || $curlopt == NULL || !is_int($curlopt)) {
        $message = sprintf("%s is not a valid curl option (see https://secure.php.net/manual/en/function.curl-setopt.php)", $curl_option['name']);
        throw new MigrateException($message);
      }

      curl_setopt($handle, $curlopt, $curl_option['value']);
    }
  }

}
