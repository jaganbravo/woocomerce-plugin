# Setting Up GitHub MCP Server in Cursor

MCP (Model Context Protocol) allows AI assistants to interact with external services like GitHub. This guide shows how to add GitHub MCP server to Cursor.

---

## What is MCP?

MCP (Model Context Protocol) is a protocol that enables AI assistants to:
- Access external tools and services
- Read/write to GitHub repositories
- Search code, create issues, manage PRs
- Access file systems and databases

---

## Step 1: Install GitHub MCP Server

### Option A: Using npm (Recommended)

```bash
# Install globally
npm install -g @modelcontextprotocol/server-github

# Or install locally in your project
npm install @modelcontextprotocol/server-github
```

### Option B: Using npx (No installation needed)

You can use it directly with npx without installing:

```bash
npx @modelcontextprotocol/server-github
```

---

## Step 2: Get GitHub Personal Access Token

1. Go to GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   - Or visit: https://github.com/settings/tokens

2. Click "Generate new token (classic)"

3. Set token name: `Cursor MCP GitHub`

4. Select scopes (permissions):
   - ✅ `repo` (Full control of private repositories)
   - ✅ `read:org` (Read org membership, if needed)
   - ✅ `read:user` (Read user profile)

5. Click "Generate token"

6. **Copy the token immediately** (you won't see it again!)

---

## Step 3: Configure Cursor MCP Settings

### Find Cursor Configuration File

Cursor stores MCP configuration in:
- **macOS**: `~/Library/Application Support/Cursor/User/globalStorage/rooveterinaryinc.roo-cline/settings/cline_mcp_settings.json`
- **Windows**: `%APPDATA%\Cursor\User\globalStorage\rooveterinaryinc.roo-cline\settings\cline_mcp_settings.json`
- **Linux**: `~/.config/Cursor/User/globalStorage/rooveterinaryinc.roo-cline/settings/cline_mcp_settings.json`

### Create/Edit MCP Configuration

Create or edit the MCP settings file:

```json
{
  "mcpServers": {
    "github": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-github"
      ],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "your_github_token_here"
      }
    }
  }
}
```

**Important**: Replace `your_github_token_here` with your actual GitHub token.

---

## Step 4: Alternative Configuration (Using npm)

If you installed globally with npm:

```json
{
  "mcpServers": {
    "github": {
      "command": "mcp-server-github",
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "your_github_token_here"
      }
    }
  }
}
```

---

## Step 5: Restart Cursor

After saving the configuration:
1. Close Cursor completely
2. Reopen Cursor
3. The GitHub MCP server should now be available

---

## Step 6: Verify Setup

Once Cursor restarts, you should be able to:
- Ask me to read GitHub files
- Create GitHub issues
- Search repositories
- Manage pull requests
- Access repository information

Try asking: "What files are in the root of this repository?"

---

## Security Best Practices

### 1. Use Environment Variables (Recommended)

Instead of hardcoding the token, use environment variables:

```json
{
  "mcpServers": {
    "github": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-github"
      ],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "${GITHUB_TOKEN}"
      }
    }
  }
}
```

Then set the environment variable:
```bash
# macOS/Linux
export GITHUB_TOKEN="your_token_here"

# Windows (PowerShell)
$env:GITHUB_TOKEN="your_token_here"
```

### 2. Use Minimal Permissions

Only grant the minimum permissions needed:
- For read-only: `public_repo`, `read:user`
- For full access: `repo` (use carefully)

### 3. Token Rotation

- Rotate tokens regularly (every 90 days)
- Revoke old tokens when creating new ones
- Use different tokens for different purposes

---

## Troubleshooting

### MCP Server Not Working

1. **Check token is valid:**
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/user
   ```

2. **Check Cursor logs:**
   - Open Cursor → Help → Toggle Developer Tools
   - Check Console for MCP errors

3. **Verify configuration syntax:**
   - Ensure JSON is valid
   - Check file path is correct
   - Verify command/args are correct

### Token Issues

- **"Bad credentials"**: Token is invalid or expired
- **"Not enough permissions"**: Token lacks required scopes
- **"Token not found"**: Environment variable not set correctly

### Command Not Found

If `npx` or `mcp-server-github` not found:
- Install Node.js: https://nodejs.org/
- Verify npm is in PATH: `which npm` or `where npm`

---

## Example MCP Configuration (Full)

Here's a complete example with multiple MCP servers:

```json
{
  "mcpServers": {
    "github": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-github"
      ],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "ghp_your_token_here"
      }
    },
    "filesystem": {
      "command": "npx",
      "args": [
        "-y",
        "@modelcontextprotocol/server-filesystem",
        "/path/to/allowed/directory"
      ]
    }
  }
}
```

---

## Available GitHub MCP Tools

Once configured, I can use these GitHub tools:

1. **Repository Operations**
   - List repositories
   - Get repository info
   - Search repositories

2. **File Operations**
   - Read files
   - Search code
   - Get file contents

3. **Issues & PRs**
   - Create issues
   - List issues
   - Create pull requests
   - Manage PRs

4. **Search**
   - Search code
   - Search repositories
   - Search issues

---

## Quick Start Commands

After setup, you can ask me:

- "List all files in this repository"
- "Create a GitHub issue titled 'Bug: Fix API key security'"
- "Search for 'class-dataviz-ai' in this repository"
- "What's the latest commit message?"
- "Create a pull request from branch X to main"

---

## Resources

- **GitHub MCP Server**: https://github.com/modelcontextprotocol/servers/tree/main/src/github
- **MCP Documentation**: https://modelcontextprotocol.io/
- **GitHub API Docs**: https://docs.github.com/en/rest

---

## Your Current Repository

Based on your git remote, your repository is:
- **URL**: `https://github.com/jaganbravo/woocomerce-plugin`
- **Name**: `woocomerce-plugin`

Once MCP is set up, I can help you:
- Manage issues and PRs
- Search code across the repository
- Read and analyze files
- Create documentation
- And more!

---

**Last Updated**: December 9, 2025  
**Status**: Setup Guide



