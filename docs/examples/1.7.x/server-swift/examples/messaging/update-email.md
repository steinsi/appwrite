import Appwrite

let client = Client()
    .setEndpoint("https://<REGION>.cloud.appwrite.io/v1") // Your API Endpoint
    .setProject("<YOUR_PROJECT_ID>") // Your project ID
    .setKey("<YOUR_API_KEY>") // Your secret API key

let messaging = Messaging(client)

let message = try await messaging.updateEmail(
    messageId: "<MESSAGE_ID>",
    topics: [], // optional
    users: [], // optional
    targets: [], // optional
    subject: "<SUBJECT>", // optional
    content: "<CONTENT>", // optional
    draft: false, // optional
    html: false, // optional
    cc: [], // optional
    bcc: [], // optional
    scheduledAt: "", // optional
    attachments: [] // optional
)

