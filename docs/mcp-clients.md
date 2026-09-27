# Sniper MCP client access

This page gives copyable client configuration for the Sniper MCP endpoint. Keep
the bearer token outside checked-in files and replace the URL with the endpoint
that the client can reach.

```sh
export SNIPER_MCP_TOKEN='replace-with-your-extension-token'
export SNIPER_MCP_URL='http://127.0.0.1:8000/mcp'
```

Use the public URL when the client runs outside the Sniper host:

```sh
export SNIPER_MCP_URL='https://sniper.fahra.my.id/mcp'
```

The local URL is for clients running on the same machine. Claude Web and remote
Claude connectors run from Anthropic infrastructure and therefore require the
public HTTPS URL. Do not put a token in a URL query string.

## OpenClaw

Add the server under `mcp.servers` in `~/.openclaw/openclaw.json`. Select
Streamable HTTP explicitly; OpenClaw otherwise defaults a remote URL to legacy
SSE.

```json5
{
  mcp: {
    servers: {
      sniper: {
        url: "https://sniper.fahra.my.id/mcp",
        transport: "streamable-http",
        headers: {
          Authorization: "Bearer ${SNIPER_MCP_TOKEN}",
        },
        connectionTimeoutMs: 10000,
        requestTimeoutMs: 60000,
        toolFilter: {
          include: [
            "get_projects",
            "get_endpoints",
            "get_payloads",
            "search_traffic",
            "get_sessions",
            "get_session_events",
            "get_workflow_map",
            "get_capture_stats",
          ],
        },
      },
    },
  },
}
```

Check the saved definition without connecting with `openclaw mcp status --verbose`.
Use `openclaw mcp probe sniper --json` for a live connection and tool listing.

## Codex CLI and IDE

Add this table to `~/.codex/config.toml` or a trusted project-scoped
`.codex/config.toml`:

```toml
[mcp_servers.sniper]
url = "https://sniper.fahra.my.id/mcp"
bearer_token_env_var = "SNIPER_MCP_TOKEN"
required = false
startup_timeout_sec = 30
tool_timeout_sec = 60
enabled_tools = ["get_projects", "get_endpoints", "get_payloads", "search_traffic", "get_sessions", "get_session_events", "get_workflow_map", "get_capture_stats"]
```

The equivalent command is:

```sh
codex mcp add sniper --url "$SNIPER_MCP_URL"
```

For a bearer token, keep `bearer_token_env_var` in `config.toml` rather than
passing the token on the command line. Codex's remote MCP configuration is
Streamable HTTP; do not select SSE for this endpoint.

## Hermes Agent

Add this entry to `~/.hermes/config.yaml`:

```yaml
mcp_servers:
  sniper:
    url: "https://sniper.fahra.my.id/mcp"
    transport: streamable-http
    protocol: auto
    headers:
      Authorization: "Bearer ${SNIPER_MCP_TOKEN}"
    connect_timeout: 10
    timeout: 60
    tools:
      include:
        - get_projects
        - get_endpoints
        - get_payloads
        - search_traffic
        - get_sessions
        - get_session_events
        - get_workflow_map
        - get_capture_stats
      resources: false
      prompts: false
```

Hermes uses `protocol: auto` to try the current handshake and fall back when a
server only supports the legacy lifecycle. Set `transport: sse` only for a
separate legacy `/sse` endpoint. If an older Sniper deployment fails Hermes'
content-type preflight while the endpoint is otherwise reachable, temporarily
add `skip_preflight: true` and verify the live probe before relying on it.

## Claude Code

Put this in the project `.mcp.json`, or add the same object with
`claude mcp add-json`:

```json
{
  "mcpServers": {
    "sniper": {
      "type": "http",
      "url": "https://sniper.fahra.my.id/mcp",
      "headers": {
        "Authorization": "Bearer ${SNIPER_MCP_TOKEN}"
      }
    }
  }
}
```

Verify with `claude mcp get sniper` and `/mcp` inside Claude Code. Claude Code
uses HTTP/Streamable HTTP for this entry. Its SSE transport is a legacy fallback,
so this URL should not be configured as an SSE URL.

## Claude Desktop and Claude Web

For a remote Sniper server, use the custom connector flow in Claude Desktop or
Claude Web: open **Settings → Connectors → Add custom connector**, then enter
the public URL:

```text
https://sniper.fahra.my.id/mcp
```

Remote connectors are called from Anthropic's cloud and cannot reach
`localhost`. Configure the connector's supported OAuth authorization flow when
the deployment uses OAuth. A static Sniper bearer token is intended for local
client configuration; do not paste it into a shared or checked-in document.

For API-based Claude access, the Messages API MCP connector uses this shape. The
URL must be public HTTPS and the token is supplied at runtime:

```json
{
  "mcp_servers": [
    {
      "type": "url",
      "url": "https://sniper.fahra.my.id/mcp",
      "name": "sniper",
      "authorization_token": "${SNIPER_MCP_TOKEN}"
    }
  ],
  "tools": [
    {
      "type": "mcp_toolset",
      "mcp_server_name": "sniper"
    }
  ],
  "betas": ["mcp-client-2025-11-20"]
}
```

## OpenCode

Add this server to `opencode.jsonc` (project) or the global OpenCode config:

```jsonc
{
  "$schema": "https://opencode.ai/config.json",
  "mcp": {
    "servers": {
      "sniper": {
        "type": "remote",
        "url": "https://sniper.fahra.my.id/mcp",
        "oauth": false,
        "headers": {
          "Authorization": "Bearer {env:SNIPER_MCP_TOKEN}"
        },
        "protocol": "auto",
        "timeout": {
          "startup": 30000,
          "catalog": 30000,
          "execution": 60000
        }
      }
    }
  }
}
```

OpenCode remote servers use Streamable HTTP. `protocol: auto` probes the modern
revision and falls back to the classic `initialize` handshake. Use
`opencode mcp add sniper --url "$SNIPER_MCP_URL"`, then inspect the connection
with `opencode mcp list`.

## Local stdio adapter

All local clients can use the tracked adapter when they cannot reach the HTTP
endpoint directly. It proxies JSON-RPC over stdin/stdout to the same Sniper
HTTP endpoint and keeps the bearer token in the process environment:

```sh
export SNIPER_MCP_URL='http://127.0.0.1:8000/mcp'
export SNIPER_MCP_TOKEN='replace-with-your-extension-token'
php /ABSOLUTE/PATH/TO/sniper/xyzsniper/bin/mcp-stdio.php
```

Use the adapter as a `command` with `env`/environment variables in Claude
Desktop, Hermes, OpenCode, or Codex stdio configuration. Do not put the token
in the command arguments.

## Smoke check

Run this from a machine that can reach the selected URL. It checks the HTTP
authentication and MCP initialize response; a successful HTTP status alone is
not proof that tool discovery or a tool call works.

```sh
curl --fail-with-body --silent --show-error \
  -H "Authorization: Bearer ${SNIPER_MCP_TOKEN}" \
  -H 'Accept: application/json, text/event-stream' \
  -H 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-11-25","capabilities":{},"clientInfo":{"name":"sniper-smoke","version":"1.0.0"}}}' \
  "${SNIPER_MCP_URL}"
```

After initialize, use the client's live probe or send a `tools/list` request
through that same MCP session. Keep the token scoped to the Sniper account and
rotate it if it is copied outside the intended client.

## Client support summary

| Client | Local transport | Remote transport | Auth/config note |
| --- | --- | --- | --- |
| OpenClaw | stdio | Streamable HTTP or legacy SSE | Set `transport: streamable-http`; `${ENV_VAR}` headers are supported |
| Codex | stdio | Streamable HTTP | Use `bearer_token_env_var` or HTTP headers; SSE is not a documented Codex transport |
| Hermes | stdio | Streamable HTTP or SSE | `protocol: auto` handles legacy/current lifecycle negotiation |
| Claude Code | stdio | HTTP/Streamable HTTP, SSE fallback | `.mcp.json` needs `type: http` and a URL |
| Claude Desktop/Web | local Desktop extensions or connectors | Public remote HTTP/Streamable HTTP | Remote connectors run from Anthropic infrastructure; localhost is not reachable |
| OpenCode | local stdio | Streamable HTTP | Remote entries use `type: remote`; OAuth is on by default unless disabled |

## Official references

- [OpenClaw MCP transports](https://docs.openclaw.ai/cli/mcp/transports)
- [Codex MCP](https://developers.openai.com/codex/mcp/)
- [Hermes MCP configuration](https://hermes-agent.nousresearch.com/docs/reference/mcp-config-reference)
- [Claude Code MCP](https://code.claude.com/docs/en/mcp)
- [Claude MCP connector](https://platform.claude.com/docs/en/agents-and-tools/mcp-connector)
- [OpenCode MCP servers](https://opencode.ai/v2/docs/mcp-servers)
- [MCP lifecycle](https://modelcontextprotocol.io/specification/2025-11-25/basic/lifecycle) and [transports](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports)
