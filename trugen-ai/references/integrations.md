# TruGen AI Integration & Ecosystem Guide

This reference details the mechanisms for connecting TruGen AI with frontend interfaces, WebRTC infrastructure, and external event hooks.

---

## Table of Contents
1. [LiveKit WebRTC Integration](#livekit-webrtc-integration)
2. [Webhooks & Events Reference](#webhooks--events-reference)
3. [Interruption & Pacing Logic](#interruption--pacing-logic)
4. [Frontend Embeds](#frontend-embeds)
5. [No-Code Ecosystem](#no-code-ecosystem)

---

## LiveKit WebRTC Integration

TruGen AI integrates directly with LiveKit agents to stream live video avatars alongside standard audio streams.

### Setup Instructions
1. **API Key Setup**: Export your key to the environment before starting the agent:
   ```bash
   export TRUGEN_API_KEY="your_api_key_here"
   ```
2. **Avatar Session Instantiation**: Import and initialize the TruGen avatar session within your LiveKit entrypoint.
3. **Graceful Shutdown**: Always trigger room disconnection upon call exit to terminate avatar neural rendering immediately and avoid billing overages:
   ```python
   # Ensure room disconnection on exit
   await ctx.room.disconnect()
   ```

---

## Webhooks & Events Reference

TruGen delivers real-time event updates to your application's backend.

### Payload Schema
All webhooks conform to the following JSON structure:
```json
{
  "timestamp": 1717502400.123,
  "conversation_id": "c83b2a53-266b-4b43-a71b-7ea8b5e2e916",
  "type": "pipeline",
  "event": {
    "name": "agent.started_speaking",
    "payload": {
      "text": "Hello! How can I help you today?"
    }
  }
}
```

### Event Catalog

| Event Name | Description | Payload Attributes |
|---|---|---|
| `agent.started_speaking` | The agent begins vocalizing text. | `payload.text` (str) |
| `agent.stopped_speaking` | The agent completes its verbal utterance. | `payload.text` (str) |
| `agent.interrupted` | User speech cut off the agent mid-sentence. | *(Reserved / Empty)* |
| `utterance_committed` | Streaming STT (Deepgram) transcription is finalized. | `payload.text` (str) |
| `participant_left` | The user has disconnected. | `payload.id` (str) |
| `call_ended` | The call session is fully concluded. | *(Reserved / Empty)* |
| `max_call_duration_timeout` | The session was terminated for exceeding limits. | `payload.call_duration` (float) |

---

## Interruption & Pacing Logic

To replicate natural human conversation, TruGen uses event-driven feedback loops:

1. **Active Interruption**:
   - The event `user.started_speaking` is monitored by the orchestrator.
   - When this event fires, the system **immediately halts neural rendering** on Huma-1 and stops TTS output, allowing the user to take over the turn.
2. **Post-Turn Downstream Action**:
   - The event `utterance_committed` signals that a complete sentence has been finalized by Deepgram.
   - Use this event to trigger background jobs like CRM updates, ticket logging, sentiment analysis, or analytics.

---

## Frontend Embeds

Deploying TruGen on web applications can be done via two main approaches:

### 1. iFrame Embed (Zero-Setup)
Best for fast prototyping. The iFrame automatically requests permissions for camera/microphone and accesses vision systems.
```html
<iframe 
  src="https://app.trugen.ai/agent/b63c2a53-266b-4b43-a71b-7ea8b5e2e916" 
  allow="camera; microphone; display-capture" 
  width="100%" 
  height="600px">
</iframe>
```

### 2. Widget Embed (Custom Layout)
Best for embedding a clean floating window. Import the JavaScript library and pass config variables.
```html
<script src="https://app.trugen.ai/widget.js"></script>
<script>
  TruGenWidget.init({
    agentId: "b63c2a53-266b-4b43-a71b-7ea8b5e2e916",
    apiKey: "YOUR_PUBLIC_WIDGET_KEY"
  });
</script>
```

---

## No-Code Ecosystem

For workflows requiring no backend code:
- **Make.com & n8n**: Leverage TruGen integration nodes.
- **Triggers**: Start workflows on events (e.g., `call_ended` or `utterance_committed`).
- **Actions**: Push lead information, conversation transcripts, and user details directly into tools like HubSpot, Salesforce, or Google Sheets.
