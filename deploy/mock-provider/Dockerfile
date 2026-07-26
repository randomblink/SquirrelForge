FROM node:20-alpine

WORKDIR /mock-provider
COPY deploy/mock-provider/server.mjs ./server.mjs

USER node
EXPOSE 8090
CMD ["node", "server.mjs"]
