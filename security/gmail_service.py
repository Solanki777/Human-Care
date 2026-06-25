import os.path
import re
from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

SCOPES = ['https://www.googleapis.com/auth/gmail.modify']
def get_gmail_service():
    creds = None

    # Load existing token
    if os.path.exists('token.json'):
        creds = Credentials.from_authorized_user_file('token.json', SCOPES)

    # If no valid credentials, login again
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            flow = InstalledAppFlow.from_client_secrets_file(
                'credentials.json', SCOPES
            )
            creds = flow.run_local_server(port=0)

        # Save token for future use
        with open('token.json', 'w') as token:
            token.write(creds.to_json())

    # Build Gmail API service
    service = build('gmail', 'v1', credentials=creds)
    return service
def get_email_body(service, msg_id):
    message = service.users().messages().get(
        userId='me',
        id=msg_id,
        format='full'
    ).execute()

    parts = message['payload'].get('parts', [])
    body = ""

    if parts:
        for part in parts:
            if part['mimeType'] == 'text/plain':
                data = part['body'].get('data')
                if data:
                    import base64
                    body += base64.urlsafe_b64decode(data).decode('utf-8', errors='ignore')
    else:
        data = message['payload']['body'].get('data')
        if data:
            import base64
            body = base64.urlsafe_b64decode(data).decode('utf-8', errors='ignore')

    return body

def _list_all_unread(service, label_id):
    """
    Returns EVERY unread message for a given label, paging through
    nextPageToken until Gmail has nothing left to give us. The old
    version capped at maxResults=10 and didn't filter by read status
    at all (labelIds alone returns read + unread), so it silently
    missed unread mail once you had more than 10 messages, and
    included already-read mail too.
    """
    messages = []
    page_token = None

    while True:
        request_args = {
            'userId': 'me',
            'labelIds': [label_id],
            'q': 'is:unread',       # <-- actually filter to unread
            'maxResults': 100,      # Gmail's max per page
        }
        if page_token:
            request_args['pageToken'] = page_token

        results = service.users().messages().list(**request_args).execute()
        messages.extend(results.get('messages', []))

        page_token = results.get('nextPageToken')
        if not page_token:
            break

    return messages


def fetch_unread_emails(service):
    all_messages = []

    # 1️⃣ ALL unread emails from INBOX (no 10-message cap)
    all_messages.extend(_list_all_unread(service, 'INBOX'))

    # 2️⃣ ALL unread emails from SPAM
    all_messages.extend(_list_all_unread(service, 'SPAM'))

    return all_messages
import re  # ADD THIS AT TOP

def get_email_details(service, msg_id):
    message = service.users().messages().get(
        userId='me',
        id=msg_id,
        format='metadata',
        metadataHeaders=['Subject', 'From']
    ).execute()

    headers = message['payload']['headers']

    subject = ""
    sender_raw = ""

    for header in headers:
        if header['name'] == 'Subject':
            subject = header['value']
        if header['name'] == 'From':
            sender_raw = header['value']

    # 🔥 Extract clean email ID
    match = re.search(r'<(.+?)>', sender_raw)
    if match:
        sender_email = match.group(1)
    else:
        sender_email = sender_raw

    return subject, sender_email
def move_to_spam(service, msg_id):
    service.users().messages().modify(
        userId='me',
        id=msg_id,
        body={
            'removeLabelIds': ['INBOX'],
            'addLabelIds': ['SPAM']
        }
    ).execute()

def get_or_create_label(service, label_name="Nexora_AI_Agent"):
    labels = service.users().labels().list(userId='me').execute()
    
    for label in labels['labels']:
        if label['name'] == label_name:
            return label['id']

    # Create label if not exists
    label_object = {
        'name': label_name,
        'labelListVisibility': 'labelShow',
        'messageListVisibility': 'show'
    }

    created_label = service.users().labels().create(
        userId='me',
        body=label_object
    ).execute()

    return created_label['id']