"""Execute production read queries against adversarial ownership fixtures.
Run: python tests/security/ownership_queries.py
SQLite validates portable read-query semantics; this does not replace MySQL integration tests.
"""
from pathlib import Path
import re
import sqlite3
import unittest

ROOT = Path(__file__).resolve().parents[2]


def query(repository, method):
    source = (ROOT / 'app/Repositories' / f'{repository}.php').read_text()
    body = source.split(f'function {method}(', 1)[1].split('\n\tpublic function ', 1)[0]
    match = re.search(r"(?:prepare\(|\$sql = )'((?:\\.|[^'])*)'", body)
    if not match:
        raise AssertionError(f'No SQL found for {method}')
    sql = match.group(1).replace("\\'", "'")
    if sql.rstrip().endswith('LIMIT'):
        sql += '50'
    return sql


class OwnershipQueries(unittest.TestCase):
    def setUp(self):
        self.db = sqlite3.connect(':memory:')
        self.db.row_factory = sqlite3.Row
        self.db.executescript('''
            CREATE TABLE monitors (id INTEGER, user_id INTEGER);
            INSERT INTO monitors VALUES (10,1),(20,2),(30,1);
            CREATE TABLE documents (id INTEGER, monitor_id INTEGER, title TEXT, description TEXT,
              storage_type TEXT, text_content TEXT, stored_filename TEXT, original_filename TEXT,
              mime_type TEXT, file_size_bytes INTEGER, created_at TEXT, updated_at TEXT);
            INSERT INTO documents VALUES (100,10,'mine','','text','mine',NULL,NULL,NULL,4,'',''),
              (200,20,'private','','text','secret',NULL,NULL,NULL,6,'',''),
              (300,30,'other monitor','','text','other',NULL,NULL,NULL,5,'','');
            CREATE TABLE monitor_contacts (id INTEGER, monitor_id INTEGER);
            INSERT INTO monitor_contacts VALUES (11,10),(22,20);
            CREATE TABLE document_monitor_contacts (document_id INTEGER, monitor_contact_id INTEGER);
            INSERT INTO document_monitor_contacts VALUES (100,11),(200,22),(200,11),(300,11);
            CREATE TABLE mail_queue (id INTEGER, user_id INTEGER, mail_type TEXT, recipient_email TEXT,
              subject TEXT, status TEXT, attempt_count INTEGER, max_attempts INTEGER, last_error TEXT,
              available_at TEXT, sent_at TEXT, failed_at TEXT, cancelled_at TEXT, created_at TEXT, updated_at TEXT);
            INSERT INTO mail_queue (id,user_id,recipient_email,subject) VALUES
              (1,1,'mine@example.test','mine'),(2,2,'private@example.test','secret');
        ''')

    def rows(self, repo, method, **bindings):
        return self.db.execute(query(repo, method), bindings).fetchall()

    def test_document_read_requires_both_owner_and_monitor(self):
        for owner in (1, 2):
            for monitor in (10, 20, 30):
                for document in (100, 200, 300, 999):
                    found = self.rows('DocumentRepository', 'FindByIdForMonitorAndUser',
                                      user_id=owner, monitor_id=monitor, document_id=document)
                    self.assertEqual(bool(found), (owner, monitor, document) in
                                     ((1,10,100), (2,20,200), (1,30,300)))
        self.assertEqual([], self.rows('DocumentRepository', 'FindAllByMonitorIdForUser', user_id=1, monitor_id=20))

    def test_recipient_preview_rejects_foreign_and_wrong_monitor_assignments(self):
        for method in ('FindAssignedDocumentIdsForUser', 'FindAssignedDocumentsForUser'):
            found = self.rows('RecipientRepository', method, id=11, user_id=1)
            self.assertEqual([100], [row[0] for row in found])
            self.assertEqual([], self.rows('RecipientRepository', method, id=22, user_id=1))
        for document in (100,200,300,999):
            found = self.rows('RecipientRepository', 'FindAssignedDocumentForUser',
                              recipient_id=11, document_id=document, user_id=1)
            self.assertEqual(document == 100, bool(found))
        self.assertEqual([], self.rows('RecipientRepository', 'FindAssignedDocumentForUser',
                                     recipient_id=22, document_id=200, user_id=1))

    def test_mail_details_never_include_other_owners(self):
        for owner in (1,2):
            found = self.rows('MailQueueRepository', 'FindRecentForUser', user_id=owner)
            self.assertEqual([owner], [row['id'] for row in found])
        controller = (ROOT / 'app/Controllers/AdministrationController.php').read_text()
        self.assertIn("'mailQueueEntries' => $this->_mailQueueRepository->FindRecentForUser((int)$user['id'], 50)", controller)
        self.assertNotIn('->FindRecent(50)', controller)


if __name__ == '__main__':
    unittest.main()
