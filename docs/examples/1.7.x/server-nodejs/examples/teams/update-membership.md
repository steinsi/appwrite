const sdk = require('node-appwrite');

const client = new sdk.Client()
    .setEndpoint('https://<REGION>.cloud.appwrite.io/v1') // Your API Endpoint
    .setProject('<YOUR_PROJECT_ID>') // Your project ID
    .setSession(''); // The user session to authenticate with

const teams = new sdk.Teams(client);

const result = await teams.updateMembership(
    '<TEAM_ID>', // teamId
    '<MEMBERSHIP_ID>', // membershipId
    [] // roles
);
