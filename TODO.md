# TODO

- [ ] Update `manage-profile-process.php` to actually update `user_id` (add `SET user_id=?`), and keep `WHERE user_id=?` based on the original id.
- [ ] Add duplicate check when changing `user_id` (since it’s a primary key).
- [ ] Update `manageprofile.php` so the form posts both:
  - the original user id (hidden, used for WHERE)
  - the new student id (separate name, used for SET)
- [ ] Quick test flow:
  - update profile with only fname/lname
  - update student id to a new value
  - attempt to change to an already-used student id

