# Customer Onboarding Flow
## Dataviz AI for WooCommerce

This document outlines the complete customer onboarding experience for the Dataviz AI WooCommerce plugin.

---

## Overview

The onboarding flow guides new users through:
1. **Welcome & Introduction** - What the plugin does
2. **API Configuration** - Setting up the API key
3. **Settings Overview** - Understanding configuration options
4. **First Interaction** - Trying the AI chat for the first time
5. **Feature Discovery** - Learning about key features

---

## Onboarding Flow Steps

### Step 1: Welcome Screen (First Visit)

**Trigger**: User visits the Dataviz AI admin page for the first time

**Display**:
- Welcome message with plugin overview
- Key benefits and use cases
- "Get Started" button to begin onboarding
- "Skip" option for experienced users

**Content**:
```
Welcome to Dataviz AI for WooCommerce! 🎉

Transform your store data into actionable insights with AI-powered analytics.

What you can do:
• Ask questions about your store in natural language
• Get instant insights on orders, products, customers, and more
• Visualize data with interactive charts
• Track trends and patterns automatically

Ready to get started?
```

**Actions**:
- [Get Started] → Proceed to Step 2
- [Skip] → Dismiss onboarding, show normal interface
- [Learn More] → Show feature overview modal

---

### Step 2: API Configuration Check

**Trigger**: After clicking "Get Started" or if API key is missing

**Display**:
- Check if API key is configured
- Show configuration status
- Provide setup instructions

**Scenarios**:

#### Scenario A: API Key Not Configured
```
⚠️ API Key Required

To use Dataviz AI, you need to configure your API key.

Option 1: Environment Variable (Recommended)
Set one of these environment variables:
• OPENAI_API_KEY
• DATAVIZ_AI_API_KEY

Option 2: Config File
Edit config.php in the plugin directory

[Configure API Key] [Skip for Now]
```

#### Scenario B: API Key Configured
```
✅ API Key Configured

Great! Your API key is set up and ready to use.

[Continue] [Test Connection]
```

**Actions**:
- [Configure API Key] → Show detailed setup instructions
- [Test Connection] → Verify API key works
- [Continue] → Proceed to Step 3
- [Skip for Now] → Allow limited functionality

---

### Step 3: Settings Overview

**Trigger**: After API configuration or if already configured

**Display**:
- Overview of plugin settings
- Key configuration options
- Links to settings page

**Content**:
```
Settings Overview

Your plugin is configured with:
• API Endpoint: [URL or Default]
• Model: [Model Name]
• Features Enabled: [List]

Key Settings:
• API Configuration
• Data Access Permissions
• Chat History Settings

[View Settings] [Continue]
```

**Actions**:
- [View Settings] → Navigate to settings page
- [Continue] → Proceed to Step 4

---

### Step 4: First Interaction Tutorial

**Trigger**: After settings overview

**Display**:
- Interactive tutorial overlay
- Highlighted chat interface
- Example questions to try

**Content**:
```
Try Your First Question! 💬

The AI assistant is ready to help you understand your store data.

Try asking:
• "What are my top-selling products?"
• "Show me orders from last week"
• "How many customers did I get this month?"

[Try Example Question] [I'll Ask My Own] [Skip Tutorial]
```

**Interactive Elements**:
- Highlight chat input box
- Show example questions as clickable suggestions
- Animate first question submission
- Show response preview

**Actions**:
- [Try Example Question] → Auto-fill and submit example
- [I'll Ask My Own] → Focus input, dismiss tutorial
- [Skip Tutorial] → Proceed to Step 5

---

### Step 5: Feature Discovery

**Trigger**: After first interaction or tutorial skip

**Display**:
- Feature highlights
- Quick tips and tricks
- Links to documentation

**Content**:
```
Discover Features 🚀

What you can do with Dataviz AI:

📊 Charts & Visualizations
Ask for charts to visualize your data
Example: "Show me a pie chart of order status"

📈 Data Analysis
Get insights on orders, products, customers
Example: "What are my best-selling products?"

💬 Chat History
Your conversations are saved automatically
Access history anytime from the chat interface

📝 Feature Requests
Request new features directly from the chat
Example: "I'd like to see product reviews analysis"

[View Documentation] [Start Using] [Close]
```

**Actions**:
- [View Documentation] → Open documentation link
- [Start Using] → Dismiss onboarding, show normal interface
- [Close] → Dismiss onboarding

---

## Onboarding States

### State Management

The plugin tracks onboarding completion using user meta:

```php
// Check if user has completed onboarding
$onboarding_completed = get_user_meta( $user_id, 'dataviz_ai_onboarding_completed', true );

// Mark onboarding as completed
update_user_meta( $user_id, 'dataviz_ai_onboarding_completed', true );
update_user_meta( $user_id, 'dataviz_ai_onboarding_completed_at', current_time( 'mysql' ) );
```

### Onboarding Status

- **Not Started**: User hasn't seen onboarding
- **In Progress**: User started but didn't complete
- **Completed**: User finished onboarding
- **Skipped**: User explicitly skipped onboarding

---

## User Experience Flow

### First-Time User Journey

```
1. Plugin Activated
   ↓
2. User visits Dataviz AI page
   ↓
3. Welcome Screen (Step 1)
   ↓
4. API Configuration Check (Step 2)
   ↓
5. Settings Overview (Step 3)
   ↓
6. First Interaction Tutorial (Step 4)
   ↓
7. Feature Discovery (Step 5)
   ↓
8. Normal Interface (Onboarding Complete)
```

### Returning User Journey

```
1. User visits Dataviz AI page
   ↓
2. Check onboarding status
   ↓
3. If completed: Show normal interface
   ↓
4. If not completed: Resume from last step
   ↓
5. Option to restart onboarding
```

---

## Implementation Details

### Components

1. **Onboarding Wizard Class** (`class-dataviz-ai-onboarding.php`)
   - Manages onboarding state
   - Renders onboarding steps
   - Handles user interactions

2. **Onboarding JavaScript** (`admin/js/onboarding.js`)
   - Step navigation
   - Interactive tutorials
   - Progress tracking

3. **Onboarding Styles** (`admin/css/onboarding.css`)
   - Wizard styling
   - Animations
   - Responsive design

### API Endpoints

- `dataviz_ai_complete_onboarding` - Mark onboarding as complete
- `dataviz_ai_skip_onboarding` - Skip onboarding
- `dataviz_ai_reset_onboarding` - Reset onboarding (admin only)
- `dataviz_ai_get_onboarding_status` - Get current status

---

## Customization Options

### Configurable Elements

1. **Onboarding Steps**: Add/remove steps as needed
2. **Welcome Message**: Customize welcome content
3. **Example Questions**: Update example questions
4. **Feature Highlights**: Modify feature discovery content
5. **Skip Options**: Control when users can skip

### Settings

- **Force Onboarding**: Require completion before using plugin
- **Auto-start**: Automatically start onboarding on first visit
- **Show Progress**: Display progress indicator
- **Allow Skip**: Allow users to skip onboarding

---

## Best Practices

### Do's

✅ **Keep it Short**: 3-5 steps maximum
✅ **Show Value**: Focus on benefits, not features
✅ **Make it Interactive**: Let users try features during onboarding
✅ **Allow Skipping**: Don't force users through every step
✅ **Save Progress**: Remember where users left off
✅ **Mobile Friendly**: Ensure onboarding works on mobile

### Don'ts

❌ **Don't Overwhelm**: Too many steps = abandonment
❌ **Don't Block Access**: Allow skipping to main interface
❌ **Don't Repeat**: Don't show onboarding if already completed
❌ **Don't Assume Knowledge**: Explain everything clearly
❌ **Don't Ignore Mobile**: Test on mobile devices

---

## Analytics & Tracking

### Metrics to Track

1. **Onboarding Start Rate**: % of users who start onboarding
2. **Completion Rate**: % of users who complete onboarding
3. **Step Drop-off**: Where users abandon onboarding
4. **Skip Rate**: % of users who skip onboarding
5. **Time to Complete**: Average time to finish onboarding
6. **Feature Discovery**: Which features users explore first

### Events to Track

- `onboarding_started`
- `onboarding_step_completed`
- `onboarding_skipped`
- `onboarding_completed`
- `onboarding_restarted`

---

## Accessibility

### Requirements

- **Keyboard Navigation**: All steps navigable via keyboard
- **Screen Reader Support**: ARIA labels and descriptions
- **Focus Management**: Clear focus indicators
- **Color Contrast**: WCAG AA compliant
- **Skip Links**: Allow skipping to main content

---

## Localization

### Translatable Strings

All onboarding content should be translatable:
- Welcome messages
- Step titles and descriptions
- Button labels
- Example questions
- Feature descriptions

### Translation Functions

Use WordPress translation functions:
- `__()` for translatable strings
- `_e()` for echoed strings
- `esc_html__()` for escaped HTML
- `esc_attr__()` for attributes

---

## Testing Checklist

### Functional Testing

- [ ] Onboarding starts on first visit
- [ ] All steps display correctly
- [ ] Navigation between steps works
- [ ] Skip functionality works
- [ ] Progress is saved correctly
- [ ] Onboarding doesn't show after completion
- [ ] Reset onboarding works (admin)

### Browser Testing

- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge
- [ ] Mobile browsers

### Accessibility Testing

- [ ] Keyboard navigation
- [ ] Screen reader compatibility
- [ ] Focus management
- [ ] Color contrast

---

## Future Enhancements

### Potential Additions

1. **Video Tutorials**: Embed video guides in onboarding
2. **Interactive Demos**: Let users try features in sandbox
3. **Personalization**: Customize onboarding based on store type
4. **Progress Rewards**: Gamify onboarding completion
5. **Contextual Help**: Show help based on user actions
6. **Multi-language**: Support for multiple languages

---

## Support & Documentation

### Resources

- **User Guide**: Link to comprehensive user guide
- **Video Tutorials**: Link to video walkthroughs
- **FAQ**: Link to frequently asked questions
- **Support**: Link to support channels

### Help Text

Each step should include:
- Clear instructions
- Help links
- Example use cases
- Troubleshooting tips

---

**Last Updated**: [Current Date]
**Version**: 1.0.0

For implementation details, see the onboarding class files in the plugin directory.
