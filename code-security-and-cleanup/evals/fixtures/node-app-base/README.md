# Notewell

A small self-hosted notes service. It has no runtime dependencies beyond Node.js 20 or newer.

## Run

```
npm start        # serves on PORT (default 3000), bound to 127.0.0.1
npm run seed     # starts a server pre-filled with a demo user and note
npm test
```

## How it is put together

- `src/server.js` holds the route table. Each route names its handler as `module.function`.
- Handler modules live in `src/handlers/`. Every function a handler module exports is registered under that name at startup.
- Note plugins are listed in `config/plugins.json` and loaded from `src/plugins/<name>.js`. A plugin is a function that receives a note and returns it with extra fields.
- `public/` holds the browser page. It is served as static files.
- Data lives in memory and is lost on restart.
