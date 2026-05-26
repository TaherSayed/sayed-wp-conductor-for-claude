<?php
/**
 * Quick-connect snippets partial.
 *
 * Expects:
 *   string $rpc_url      The /rpc endpoint URL.
 *   string $snippet_id   Unique DOM id for this block (used for copy buttons).
 *   string $just_token   Optional plaintext token (only set right after create/rotate).
 *   string $token_view   Optional placeholder string when plaintext isn't available.
 *
 * @package WPCommander
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Partial-template locals.

$token_for_snippet = ! empty( $just_token ) ? (string) $just_token : ( isset( $token_view ) ? (string) $token_view : '<YOUR_TOKEN>' );
$site_host = (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'wordpress' );
// Sanitised client name for Claude Desktop key.
$claude_key = sanitize_key( $site_host );
if ( $claude_key === '' ) { $claude_key = 'commander'; }

$curl_snippet = sprintf(
    "curl -sS -X POST %s \\\n  -H \"Authorization: Bearer %s\" \\\n  -H \"Content-Type: application/json\" \\\n  -H \"Accept: application/json\" \\\n  -d '{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"%s\",\"clientInfo\":{\"name\":\"curl\",\"version\":\"1\"},\"capabilities\":{}}}'",
    $rpc_url,
    $token_for_snippet,
    CMCP_PROTOCOL_VERSION
);

$claude_desktop = wp_json_encode(
    [
        'mcpServers' => [
            $claude_key => [
                'url'     => $rpc_url,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token_for_snippet,
                ],
            ],
        ],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);

$python_snippet  = "import requests, json\n\n";
$python_snippet .= 'url   = "' . $rpc_url . "\"\n";
$python_snippet .= 'token = "' . $token_for_snippet . "\"\n\n";
$python_snippet .= "resp = requests.post(\n";
$python_snippet .= "    url,\n";
$python_snippet .= "    headers={\n";
$python_snippet .= "        \"Authorization\": f\"Bearer {token}\",\n";
$python_snippet .= "        \"Content-Type\":  \"application/json\",\n";
$python_snippet .= "        \"Accept\":        \"application/json\",\n";
$python_snippet .= "    },\n";
$python_snippet .= "    json={\n";
$python_snippet .= "        \"jsonrpc\": \"2.0\",\n";
$python_snippet .= "        \"id\": 1,\n";
$python_snippet .= "        \"method\": \"tools/list\",\n";
$python_snippet .= "    },\n";
$python_snippet .= "    timeout=10,\n";
$python_snippet .= ")\n";
$python_snippet .= "print(json.dumps(resp.json(), indent=2))";

$node_snippet  = 'const url   = "' . $rpc_url . "\";\n";
$node_snippet .= 'const token = "' . $token_for_snippet . "\";\n\n";
$node_snippet .= "const res = await fetch(url, {\n";
$node_snippet .= "  method: \"POST\",\n";
$node_snippet .= "  headers: {\n";
$node_snippet .= "    \"Authorization\": `Bearer \${token}`,\n";
$node_snippet .= "    \"Content-Type\":  \"application/json\",\n";
$node_snippet .= "    \"Accept\":        \"application/json\",\n";
$node_snippet .= "  },\n";
$node_snippet .= "  body: JSON.stringify({\n";
$node_snippet .= "    jsonrpc: \"2.0\",\n";
$node_snippet .= "    id: 1,\n";
$node_snippet .= "    method: \"tools/list\",\n";
$node_snippet .= "  }),\n";
$node_snippet .= "});\n";
$node_snippet .= "console.log(JSON.stringify(await res.json(), null, 2));";
?>
<details class="cmcp-snippets">
    <summary>🔌 <?php esc_html_e( 'Connect snippets', 'sayed-wp-conductor-for-claude' ); ?></summary>
    <div class="cmcp-snippets-body">
        <div class="cmcp-tabs" data-snip="<?php echo esc_attr( $snippet_id ); ?>">
            <button type="button" class="cmcp-tab active" data-tab="curl">curl</button>
            <button type="button" class="cmcp-tab"        data-tab="claude">Claude Desktop</button>
            <button type="button" class="cmcp-tab"        data-tab="python">Python</button>
            <button type="button" class="cmcp-tab"        data-tab="node">Node</button>
        </div>

        <div class="cmcp-tab-panel active" data-tab="curl">
            <div class="cmcp-snip-head">
                <span class="cmcp-snip-label">bash</span>
                <button type="button" class="button button-small cmcp-copy" data-target="#<?php echo esc_attr( $snippet_id ); ?>-curl">Copy</button>
            </div>
            <pre id="<?php echo esc_attr( $snippet_id ); ?>-curl" class="cmcp-pre"><?php echo esc_html( $curl_snippet ); ?></pre>
        </div>

        <div class="cmcp-tab-panel" data-tab="claude">
            <div class="cmcp-snip-head">
                <span class="cmcp-snip-label">~/Library/Application Support/Claude/claude_desktop_config.json (macOS) · %APPDATA%\Claude\claude_desktop_config.json (Windows)</span>
                <button type="button" class="button button-small cmcp-copy" data-target="#<?php echo esc_attr( $snippet_id ); ?>-claude">Copy</button>
            </div>
            <pre id="<?php echo esc_attr( $snippet_id ); ?>-claude" class="cmcp-pre"><?php echo esc_html( (string) $claude_desktop ); ?></pre>
        </div>

        <div class="cmcp-tab-panel" data-tab="python">
            <div class="cmcp-snip-head">
                <span class="cmcp-snip-label">Python 3 · pip install requests</span>
                <button type="button" class="button button-small cmcp-copy" data-target="#<?php echo esc_attr( $snippet_id ); ?>-python">Copy</button>
            </div>
            <pre id="<?php echo esc_attr( $snippet_id ); ?>-python" class="cmcp-pre"><?php echo esc_html( $python_snippet ); ?></pre>
        </div>

        <div class="cmcp-tab-panel" data-tab="node">
            <div class="cmcp-snip-head">
                <span class="cmcp-snip-label">Node 18+ · uses global fetch</span>
                <button type="button" class="button button-small cmcp-copy" data-target="#<?php echo esc_attr( $snippet_id ); ?>-node">Copy</button>
            </div>
            <pre id="<?php echo esc_attr( $snippet_id ); ?>-node" class="cmcp-pre"><?php echo esc_html( $node_snippet ); ?></pre>
        </div>

        <?php if ( $token_for_snippet === '<YOUR_TOKEN>' ) : ?>
            <p class="description" style="margin:6px 4px 0;color:#646970">
                <?php esc_html_e( 'Token plaintext is not stored on the server. Replace <YOUR_TOKEN> with the token shown when you issued or rotated it.', 'sayed-wp-conductor-for-claude' ); ?>
            </p>
        <?php endif; ?>
    </div>
</details>
