# Next Steps - Dataviz AI WooCommerce Plugin

This document outlines the planned improvements and features for the Dataviz AI WooCommerce plugin.

## Priority Tasks

### 1. ✅ Fix Stop Button Not Working
**Status**: In Progress  
**Description**: Debug and ensure the stop button appears and cancels the streaming response properly.

**Issues to Address**:
- Button may not be visible due to CSS/display issues
- Click handler may not be properly attached
- Stream cancellation may not be working correctly

**Implementation Notes**:
- Verify button appears when streaming starts
- Ensure `AbortController` properly cancels fetch request
- Test that partial responses are preserved when stopped
- Verify cleanup of stream readers and controllers

---

### 2. 📝 Chat History for Past 5 Days
**Status**: Pending  
**Description**: Implement persistent chat history that stores conversations for the past 5 days.

**Requirements**:
- Store chat history in WordPress user meta or custom table
- Load chat history on page load
- Send full conversation history to OpenAI API
- Automatically clean up messages older than 5 days
- Persist across page refreshes

**Implementation Approach**:
- Use WordPress `user_meta` table for per-user chat history
- Store messages as JSON array: `[{role, content, timestamp}, ...]`
- Limit to last N messages or messages from last 5 days
- Add AJAX endpoint to load/clear history

**Database Schema** (User Meta):
```php
meta_key: 'dataviz_ai_chat_history'
meta_value: JSON array of messages with timestamps
```

**Features**:
- [ ] Save each user message and AI response
- [ ] Load history when page loads
- [ ] Send history to OpenAI API in messages array
- [ ] Auto-cleanup messages older than 5 days
- [ ] Add "Clear History" button in UI

---

### 3. ❓ Frequently Asked Questions (FAQ)
**Status**: Pending  
**Description**: Add a FAQ feature to help users discover common questions and get started quickly.

**Requirements**:
- Display suggested questions/FAQs in the chat interface
- Allow users to click FAQs to auto-populate their question
- Show FAQs when chat is empty
- Make FAQs contextual to WooCommerce data

**Implementation Approach**:
- Add FAQ section above chat input
- Store FAQs in WordPress options or as constants
- Style as clickable cards/chips
- Auto-populate input on click
- Show/hide based on conversation state

**Example FAQs**:
- "What are my recent orders?"
- "Show me top-selling products"
- "What's my total revenue this month?"
- "Who are my top customers?"
- "Show me orders by status in a pie chart"

**UI Design**:
- Display when `conversationHistory.length === 0`
- Grid of clickable FAQ cards
- Hide when user starts typing or chatting

---

### 4. 🔐 OpenAI Key Not in UI
**Status**: Pending  
**Description**: Ensure OpenAI API key is never displayed or stored in the UI - only read from environment variables.

**Current State**:
- API key can be entered in settings page
- Key is stored in WordPress options database

**Requirements**:
- Remove API key input field from UI
- Only read key from environment variables (`OPENAI_API_KEY`, `DATAVIZ_AI_API_KEY`)
- Show read-only status indicator (key configured/not configured)
- Display error message if key is missing

**Implementation**:
- [ ] Remove API key field from settings form
- [ ] Update `class-dataviz-ai-api-client.php` to only check env vars
- [ ] Add status indicator showing key availability
- [ ] Update UI to show "API key configured" vs "API key missing"
- [ ] Add instructions for setting environment variable

**Security Benefits**:
- Prevents accidental exposure of API keys
- Follows security best practices
- Key remains in environment only

---

### 5. 🔬 Explore More LLM Options
**Status**: Pending  
**Description**: Research and implement support for multiple LLM providers beyond OpenAI.

**LLM Providers to Explore**:

#### 5.1 Anthropic Claude
- API: `https://api.anthropic.com/v1/messages`
- Models: `claude-3-5-sonnet-20241022`, `claude-3-opus-20240229`
- Features: Function calling (tools), streaming
- **Priority**: High (excellent for structured data)

#### 5.2 Google Gemini
- API: `https://generativelanguage.googleapis.com/v1beta`
- Models: `gemini-1.5-pro`, `gemini-1.5-flash`
- Features: Function calling, streaming
- **Priority**: Medium (competitive pricing)

#### 5.3 Groq
- API: `https://api.groq.com/openai/v1/chat/completions`
- Models: `llama-3.3-70b-versatile`, `mixtral-8x7b-32768`
- Features: Very fast inference, OpenAI-compatible API
- **Priority**: High (speed advantage)

#### 5.4 Mistral AI
- API: `https://api.mistral.ai/v1/chat/completions`
- Models: `mistral-large-latest`, `mistral-medium-latest`
- Features: Function calling, streaming
- **Priority**: Medium

#### 5.5 Cohere
- API: `https://api.cohere.ai/v1/chat`
- Models: `command-r-plus`, `command-r`
- Features: Function calling, streaming
- **Priority**: Low

**Implementation Plan**:
1. Create abstract `LLM_Provider` interface/class
2. Implement provider classes:
   - `OpenAI_Provider` (current)
   - `Anthropic_Provider`
   - `Groq_Provider`
   - `Google_Gemini_Provider`
3. Add provider selection in settings
4. Ensure all providers support:
   - Function calling/tools
   - Streaming responses
   - Similar message format

**Code Structure**:
```
includes/
  class-llm-provider-abstract.php
  class-llm-provider-openai.php
  class-llm-provider-anthropic.php
  class-llm-provider-groq.php
  class-llm-provider-google.php
```

**Configuration**:
- Environment variables per provider:
  - `OPENAI_API_KEY`
  - `ANTHROPIC_API_KEY`
  - `GROQ_API_KEY`
  - `GOOGLE_GEMINI_API_KEY`
- Provider selection in settings (or auto-detect based on available keys)

---

### 6. 📚 Explore Successful Plugin Patterns
**Status**: Pending  
**Description**: Research successful WooCommerce plugins to understand best practices, user expectations, and growth patterns.

**Research Areas**:

#### 6.1 Top WooCommerce Plugins Analysis
**Plugins to Study**:
1. **WooCommerce Subscriptions**
   - Users: 100K+ active installations
   - Features: Recurring payments, subscription management
   - UI/UX patterns
   - Pricing models

2. **YITH WooCommerce Plugins**
   - Portfolio: 100+ plugins
   - User base: Millions combined
   - Common patterns: Settings pages, admin UI, onboarding

3. **WooCommerce PDF Invoices**
   - Simple, focused functionality
   - Clean admin interface
   - Strong documentation

4. **WooCommerce Advanced Bulk Edit**
   - Admin-focused tool
   - Efficient UI patterns

**Key Metrics to Research**:
- Number of active installations
- User ratings and reviews
- Update frequency
- Support responsiveness
- Documentation quality
- Pricing strategies

#### 6.2 Best Practices to Identify

**User Experience**:
- [ ] Onboarding flow for first-time users
- [ ] Settings page organization
- [ ] Error handling and user feedback
- [ ] Loading states and progress indicators
- [ ] Mobile responsiveness

**Technical Patterns**:
- [ ] Plugin structure and organization
- [ ] Database schema patterns
- [ ] AJAX implementation
- [ ] Asset enqueuing strategies
- [ ] Security best practices

**Marketing & Growth**:
- [ ] Feature discovery mechanisms
- [ ] Upgrade prompts
- [ ] Documentation structure
- [ ] Support channels
- [ ] Freemium vs premium models

**Resources to Consult**:
- WordPress.org plugin repository
- WooCommerce plugin directory
- GitHub repositories of popular plugins
- Plugin reviews and feedback
- WooCommerce developer documentation

**Deliverables**:
- [ ] Research summary document
- [ ] UI/UX pattern recommendations
- [ ] Technical architecture insights
- [ ] Growth strategy suggestions

---

## Implementation Priority

1. **High Priority** (Fix Critical Issues):
   - ✅ Fix Stop Button
   - 🔐 Remove API Key from UI

2. **Medium Priority** (Core Features):
   - 📝 Chat History (5 days)
   - ❓ FAQ Feature

3. **Low Priority** (Enhancements):
   - 🔬 Multi-LLM Support
   - 📚 Plugin Research

---

## Notes

- **No LangChain**: We're using direct API integration (simpler, better for PHP/WordPress)
- **WordPress Native**: All features use WordPress functions and patterns
- **Security First**: API keys in environment variables only
- **User Experience**: Focus on clean, intuitive interface similar to ChatGPT

---

**Last Updated**: November 23, 2025  
**Status**: Planning Phase

