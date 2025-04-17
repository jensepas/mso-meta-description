<?php

/**
 * MSO AI Meta Description AnthropicProvider
 *
 * Implements the ProviderInterface for interacting with the Anthropic API.
 * Handles generating meta description summaries using Anthropic models.
 * Extends AbstractProvider for common functionality.
 *
 * @package MSO_AI_Meta_Description
 * @since   1.3.0
 */

namespace MSO_AI_Meta_Description\Providers\Available;

// Use the AbstractProvider and ProviderInterface
use MSO_AI_Meta_Description\Providers\AbstractProvider;
use MSO_AI_Meta_Description\Providers\ProviderInterface;
use WP_Error;

/**
 * Anthropic Provider implementation.
 *
 * Extends the AbstractProvider to inherit common API interaction logic
 * and implements ProviderInterface methods specific to Anthropic API.
 */
// Extend the abstract class
class AnthropicProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Returns the unique identifier for this provider.
     *
     * @return string The provider name ('anthropic').
     */
    public function get_name(): string
    {
        // Unique lowercase identifier for Anthropic
        return 'anthropic';
    }

    /**
     * Returns the title for this provider.
     *
     * @return string The provider title
     */
    public function get_title(): string
    {
        return 'Anthropic';
    }

    /**
     * Returns the base URL for the Anthropic API.
     *
     * @return string The base URL for Anthropic API v1.
     */
    protected function get_api_base(): string
    {
        // Base URL for the Anthropic API
        return 'https://api.anthropic.com/v1/';
    }

    /**
     * Returns the default model ID to use if none is specified.
     *
     * @return string The default Anthropic model identifier.
     */
    public function get_default_model(): string
    {
        // Default Anthropic model
        return 'claude-3-sonnet-20240229';
    }

    /**
     * Returns the specific API endpoint for generating summaries (chat completions).
     *
     * @return string The endpoint path for chat completions.
     */
    protected function get_summary_endpoint(): string
    {
        // Endpoint for generating messages (summaries)
        return 'messages';
    }

    /**
     * Extracts the error message from an Anthropic API error response.
     *
     * Anthropic typically returns errors in a nested 'error' object with a 'message' field.
     *
     * @param array<string, mixed>|null $data The decoded JSON response data, or null if decoding failed.
     * @return string The extracted error message, or null if not found.
     */
    protected function extract_error_message(?array $data): string
    {
        // Check if the expected keys exist and the message is a string
        if (isset($data['body']) && is_string($data['body'])) {
            return $data['body'];
        }

        return '';
    }

    /**
     * Fetches models.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the models endpoint.
     * @return array<int, array<string, string>>|WP_Error An array of models (each with 'id' and 'displayName')
     *                                                    or a WP_Error if parsing fails.
     */
    protected function parse_model_list(array $data): array|WP_Error
    {
        // $data is ignored as we are not fetching from API
        // Return a hardcoded list of popular Anthropic 3 models
        // Anthropic specific model list structure
        if (! isset($data['data']) || ! is_array($data['data'])) {
            $provider = $this->get_name();

            return new WP_Error(
                'parse_error',
                sprintf(
                    /* translators: 1: provider name */
                    __('Unable to parse model list from %1$d: "models" array missing.', 'mso-ai-meta-description'),
                    $provider
                )
            );
        }

        // Ensure the format matches the expected structure
        return array_map(function ($model) {
            return [
                'id' => $model['id'],
                'displayName' => $model['display_name'] ?? $model['id'],
            ];
        }, $data['data']);
    }

    /**
     * Builds the request body for the Anthropic chat completions endpoint.
     *
     * Constructs the JSON payload required by the API, including the model,
     * the user prompt, and parameters like max_tokens and temperature.
     *
     * @param string $prompt The user-provided text to generate a summary from.
     * @return array<string, mixed> The request body as an associative array, ready for JSON encoding.
     */
    protected function build_summary_request_body(string $prompt): array
    {
        // Builds the POST request body for summary generation, specific to Anthropic Messages API
        return [
            'model' => $this->model, // Uses the selected model
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'max_tokens' => 150, // Anthropic needs a reasonable max_tokens; 70 might be too low sometimes
            'temperature' => 0.6,
            // 'system' => 'You are an expert meta description writer.' // Optional system prompt
        ];
    }

    /**
     * Parses the generated summary text from the Anthropic API response.
     *
     * Extracts the content from the expected location within the chat completion response structure.
     *
     * @param array<string, mixed> $data The decoded JSON response data from the chat completions endpoint.
     * @return string|WP_Error The extracted summary text on success, or a WP_Error if parsing fails.
     */
    protected function parse_summary(array $data): string|WP_Error
    {
        // Extracts the generated summary text from Anthropic specific JSON response structure
        // Anthropic returns content as an array of blocks; we expect a single text block.
        $generated_text = null;
        if (isset($data['content'][0]['type']) && is_array($data['content']) && $data['content'][0]['type'] === 'text') {
            $generated_text = $data['content'][0]['text'] ?? null;
        }

        if ($generated_text === null) {
            // Return an error if the summary text is missing or not a string.
            $provider = $this->get_name();

            return new WP_Error(
                'parse_error',
                sprintf(
                    /* translators: 1: provider name */
                    __('%1$d response missing expected summary data or invalid format.', 'mso-ai-meta-description'),
                    $provider
                )
            );
        }

        return is_string($generated_text) ? trim($generated_text) : '';
    }

    /**
     * Overrides prepare_headers to set Anthropic-specific authentication headers.
     *
     *  @param array<string, string> $headers Default headers from AbstractProvider.
     *  @return array<string, string> Modified headers for Anthropic API.
     */
    protected function prepare_headers(array $headers): array
    {
        // Remove the default 'Authorization: Bearer' header
        unset($headers['Authorization']);

        $headers += [
            'anthropic-version' => '2023-06-01',
            'x-api-key' => $this->api_key,
        ];

        return $headers;
    }
}
