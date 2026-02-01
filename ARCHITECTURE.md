# Architecture Overview

This system behaves as an external trust platform that plugs into Pressbooks.

- Zero coupling to Pressbooks internals
- Own REST APIs, DB tables, crypto, lifecycle
- Pressbooks acts only as a session/container layer
