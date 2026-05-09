# MVP Launch Scope - Dataviz AI for WooCommerce

## 🎯 MVP Goal
Launch a working AI-powered analytics plugin that allows WooCommerce store owners to ask questions about their store data and get intelligent answers.

---

## ✅ Currently Implemented (Working)

### Core Functionality
- ✅ **Flexible Tool System** - LLM can query orders, products, customers, categories, tags, coupons, refunds, stock
- ✅ **Function Calling** - LLM decides which tools to use based on user questions
- ✅ **Streaming Responses** - Real-time chat experience (SSE)
- ✅ **Admin Dashboard** - ChatGPT-style interface in WordPress admin
- ✅ **Settings Page** - API configuration
- ✅ **Data Fetchers** - Comprehensive WooCommerce data access
- ✅ **Large Dataset Handling** - Statistics, sampling, time-series aggregation
- ✅ **Error Handling** - Basic error handling and logging

### Data Access
- ✅ Orders (list, statistics, sample, by_period)
- ✅ Products (list, statistics)
- ✅ Customers (list, statistics)
- ✅ Categories, Tags, Coupons, Refunds, Stock

---

## 🚨 Critical for MVP Launch (Must Have)

### 1. Security & Configuration
**Priority: CRITICAL**

- [ ] **Remove API Key from UI** (Security)
  - Currently: API key stored in WordPress options (visible in UI)
  - Required: Read only from environment variables
  - Impact: Security vulnerability if not fixed
  - Effort: 2-3 hours

- [ ] **Environment Variable Support**
  - Read `OPENAI_API_KEY` or `DATAVIZ_AI_API_KEY` from environment
  - Show status indicator (configured/not configured)
  - Clear error message if missing
  - Effort: 2-3 hours

### 2. User Experience Essentials
**Priority: HIGH**

- [ ] **Onboarding Flow**
  - First-time user experience
  - Check if API key is configured
  - Show setup instructions if missing
  - Effort: 3-4 hours

- [ ] **Error Messages**
  - User-friendly error messages
  - Clear guidance when API key missing
  - Handle rate limits gracefully
  - Effort: 2-3 hours

- [ ] **Loading States**
  - Show loading indicator during tool execution
  - Progress feedback for long queries
  - Effort: 1-2 hours

### 2.1 UX Acceptance Checklist (Release Gate)
**Priority: HIGH**

- [ ] **Fast first value**
  - New admin can ask a first question and get a useful answer within 3 minutes of setup.
  - Onboarding and API-key guidance are visible and unambiguous.

- [ ] **Answer trust and clarity**
  - Responses include concrete values and relevant context (for example, date range/status).
  - Avoid vague phrasing like "there are currently completed orders."

- [ ] **Guided empty-state experience**
  - Empty chat shows 3-5 high-value sample prompts.
  - Prompt chips are one-click and editable before send.

- [ ] **Graceful errors and recovery**
  - Missing key, network errors, and rate limits show actionable next steps.
  - No raw stack traces or provider-internal error blobs in UI.

- [ ] **Streaming and controls reliability**
  - Streaming remains responsive for long answers.
  - Stop/cancel works reliably and preserves partial response.

- [ ] **Chart usability**
  - Charts render only when relevant and include readable labels.
  - If chart data is unavailable, fallback to a clear textual summary.

- [ ] **Access safety**
  - Only authorized roles can access sensitive analytics endpoints and views.
  - Non-privileged users get safe denial messages.

- [ ] **Performance baseline**
  - Typical questions return first streamed tokens quickly (target under 3 seconds on local/staging baseline).
  - UI remains interactive during long-running requests.

- [ ] **Privacy transparency**
  - Privacy policy helper text is present and accurate.
  - Export/erase actions cover chat/support personal data for requested users.

- [ ] **Digest value loop**
  - Digest scheduling and delivery paths are test-verified.
  - Failed sends surface actionable diagnostics for admins.

### 3. Core Features Polish
**Priority: HIGH**

- [ ] **Stop Button Fix** (if broken)
  - Verify stop button works during streaming
  - Cancel requests properly
  - Preserve partial responses
  - Effort: 2-3 hours

- [ ] **Basic FAQ/Suggestions**
  - Show 3-5 example questions when chat is empty
  - Click to auto-populate question
  - Effort: 2-3 hours

### 4. Testing & Quality
**Priority: HIGH**

- [ ] **End-to-End Testing**
  - Test all entity types (orders, products, customers, etc.)
  - Test all query types (list, statistics, sample, by_period)
  - Test error scenarios
  - Effort: 4-6 hours

- [ ] **Browser Compatibility**
  - Test in Chrome, Firefox, Safari
  - Test streaming functionality
  - Effort: 2-3 hours

- [ ] **WooCommerce Compatibility**
  - Test with WooCommerce 6.0+
  - Test with different store sizes
  - Effort: 2-3 hours

---

## 📋 Nice to Have (Post-MVP)

### Phase 2 Features
- [ ] **Chat History** (5 days)
  - Store conversation history
  - Load on page refresh
  - Auto-cleanup old messages
  - Effort: 6-8 hours

- [ ] **Enhanced Charts**
  - LLM-generated chart JSON
  - More chart types
  - Interactive visualizations
  - Effort: 8-10 hours

- [ ] **Multi-LLM Support**
  - Support for Anthropic, Groq, Gemini
  - Provider selection in settings
  - Effort: 12-16 hours

- [ ] **Advanced Query Types**
  - `by_period` for products/customers
  - `sample` for products/customers
  - Effort: 4-6 hours

---

## 🎨 UI/UX Improvements (Post-MVP)

- [ ] Mobile responsiveness
- [ ] Dark mode support
- [ ] Keyboard shortcuts
- [ ] Export conversations
- [ ] Copy response button

---

## 📦 Pre-Launch Checklist

### Code Quality
- [ ] Remove all debug code
- [ ] Remove console.log statements
- [ ] Code comments/documentation
- [ ] Security audit (nonces, sanitization, escaping)
- [ ] PHP linting (no errors/warnings)

### Plugin Metadata
- [ ] Update plugin header (name, description, version)
- [ ] Update README.md
- [ ] Add changelog
- [ ] Add screenshots
- [ ] Add banner/icon images

### WordPress Standards
- [ ] Follow WordPress coding standards
- [ ] Proper text domain usage
- [ ] Internationalization ready
- [ ] Accessibility (WCAG basics)

### Performance
- [ ] Optimize database queries
- [ ] Minimize API calls
- [ ] Cache where appropriate
- [ ] Test with large datasets

### Documentation
- [ ] User documentation (how to use)
- [ ] Installation guide
- [ ] FAQ section
- [ ] Troubleshooting guide

---

## 🚀 MVP Launch Criteria

### Must Pass Before Launch:
1. ✅ API key read from environment variables only
2. ✅ All core entity types work (orders, products, customers)
3. ✅ Streaming responses work reliably
4. ✅ Error handling works (missing API key, rate limits)
5. ✅ Basic onboarding flow
6. ✅ Tested with real WooCommerce store
7. ✅ No critical bugs or security issues

### Launch Readiness Score:
- **Security**: ⚠️ Needs work (API key in UI)
- **Core Features**: ✅ Working
- **User Experience**: ⚠️ Needs polish (onboarding, errors)
- **Testing**: ⚠️ Needs comprehensive testing
- **Documentation**: ⚠️ Needs user docs

**Overall Status**: ~70% ready for launch

---

## ⏱️ Estimated Time to MVP Launch

### Critical Path (Must Have):
- Security fixes: 4-6 hours
- UX polish: 5-7 hours
- Testing: 8-12 hours
- Documentation: 4-6 hours

**Total: 21-31 hours** (~3-4 days of focused work)

### With Nice-to-Haves:
- Add chat history: +6-8 hours
- Enhanced FAQ: +2-3 hours
- Better error handling: +2-3 hours

**Total: 31-45 hours** (~1 week of focused work)

---

## 📊 Priority Matrix

| Feature | Priority | Effort | Impact | Launch? |
|---------|----------|--------|--------|---------|
| Remove API key from UI | CRITICAL | 2-3h | High | ✅ Yes |
| Environment variable support | CRITICAL | 2-3h | High | ✅ Yes |
| Onboarding flow | HIGH | 3-4h | High | ✅ Yes |
| Error messages | HIGH | 2-3h | Medium | ✅ Yes |
| Stop button fix | HIGH | 2-3h | Medium | ✅ Yes |
| Basic FAQ | HIGH | 2-3h | Medium | ✅ Yes |
| Testing | HIGH | 8-12h | High | ✅ Yes |
| Chat history | MEDIUM | 6-8h | Medium | ❌ Post-MVP |
| Multi-LLM support | LOW | 12-16h | Low | ❌ Post-MVP |
| Enhanced charts | LOW | 8-10h | Low | ❌ Post-MVP |

---

## 🎯 Recommended MVP Launch Plan

### Week 1: Critical Fixes
**Days 1-2**: Security & Configuration
- Remove API key from UI
- Implement environment variable support
- Add status indicators

**Days 3-4**: User Experience
- Onboarding flow
- Error messages
- Loading states
- Basic FAQ

**Day 5**: Testing
- End-to-end testing
- Browser compatibility
- WooCommerce compatibility

### Week 2: Polish & Launch
**Days 1-2**: Documentation
- User guide
- Installation instructions
- FAQ

**Days 3-4**: Final Testing
- Real-world testing
- Performance testing
- Security audit

**Day 5**: Launch Prep
- Plugin metadata
- Screenshots
- Final checks

---

## ✅ MVP Launch Checklist

### Before Launch:
- [ ] All critical features implemented
- [ ] Security issues resolved
- [ ] Tested with real WooCommerce store
- [ ] User documentation complete
- [ ] Plugin metadata updated
- [ ] No critical bugs
- [ ] Performance acceptable
- [ ] Error handling robust

### Launch Day:
- [ ] Final code review
- [ ] Version number set (1.0.0)
- [ ] Changelog updated
- [ ] Screenshots ready
- [ ] Support channels ready

---

## 📝 Notes

- **No RAG needed for MVP** - Function calling is sufficient
- **No multi-LLM needed for MVP** - OpenAI works well
- **Focus on core value**: Ask questions, get answers about store data
- **Keep it simple**: Don't over-engineer for MVP
- **Iterate based on feedback**: Add features post-launch

---

**Last Updated**: December 9, 2025  
**Status**: Ready for MVP implementation

