# How to Set Up Your OpenAI API Key

## Quick Setup

You need to update the `config.php` file in your Docker WordPress installation with your actual OpenAI API key.

### Step 1: Get Your OpenAI API Key

1. Go to https://platform.openai.com/api-keys
2. Sign in to your OpenAI account
3. Click "Create new secret key"
4. Copy the API key (it starts with `sk-...`)
5. **Important**: Save it somewhere safe - you won't be able to see it again!

### Step 2: Update the Config File

Edit this file:
```
docker/wordpress/wp-content/plugins/dataviz-ai-woocommerce-plugin/config.php
```

Change line 37 from:
```php
define( 'DATAVIZ_AI_API_KEY', 'YOUR_OPENAI_API_KEY_HERE' );
```

To:
```php
define( 'DATAVIZ_AI_API_KEY', 'sk-your-actual-api-key-here' );
```

Replace `sk-your-actual-api-key-here` with your actual API key from Step 1.

### Step 3: Verify

After updating, refresh your WordPress admin page and try using the plugin again. The error should be resolved.

## Security Notes

- ⚠️ **Never commit this file to Git** - it's already in `.gitignore`
- ⚠️ **Never share your API key** publicly
- ⚠️ Keep your API key secure and rotate it if compromised
- 💰 Monitor your API usage at https://platform.openai.com/usage to avoid unexpected charges

## Alternative: Using Environment Variable (Advanced)

If you prefer not to store the API key in the file, you can use an environment variable:

1. Add to `docker/docker-compose.yml` under the `wordpress` service:
   ```yaml
   environment:
     - OPENAI_API_KEY=sk-your-actual-api-key-here
   ```

2. Update `config.php` to check for the environment variable:
   ```php
   define( 'DATAVIZ_AI_API_KEY', getenv('OPENAI_API_KEY') ?: 'YOUR_OPENAI_API_KEY_HERE' );
   ```

3. Restart Docker containers:
   ```bash
   cd docker
   docker-compose down
   docker-compose up -d
   ```

## Troubleshooting

- **Still seeing the error?** Make sure you:
  1. Saved the file after editing
  2. The API key doesn't have extra spaces or quotes
  3. You're using the Docker WordPress config file, not the main plugin folder

- **Want to test the connection?** Visit:
  ```
  http://localhost:8080/test-openai-connection.php
  ```

