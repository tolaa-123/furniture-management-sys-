-- Update default user credentials
-- admin@furniture.com / admin123
-- manager@furniture.com / manager123
-- customer@furniture.com / customer123
-- employee@furniture.com / employee123

UPDATE furn_users SET
    email = 'admin@furniture.com',
    password_hash = '$2y$10$R9Ene/egMPkE7i6wmR72puQYarFUGYn1EIORQ8ud67DhEbj7S/Ul2',
    status = 'active', is_active = 1, failed_attempts = 0
WHERE role = 'admin' OR role_id = (SELECT id FROM roles WHERE role_name = 'admin' LIMIT 1)
LIMIT 1;

UPDATE furn_users SET
    email = 'manager@furniture.com',
    password_hash = '$2y$10$mGabhp3LuWe8ZQF5oYcX/.5uZXivrmmWfMFgwWv4qa.qOmei7aVGO',
    status = 'active', is_active = 1, failed_attempts = 0
WHERE role = 'manager' OR role_id = (SELECT id FROM roles WHERE role_name = 'manager' LIMIT 1)
LIMIT 1;

UPDATE furn_users SET
    email = 'customer@furniture.com',
    password_hash = '$2y$10$hSWa.iw9zcaOZSePhhISj.glaGDYPVsems6dXouFvTWv8M9.Vh4MK',
    status = 'active', is_active = 1, failed_attempts = 0
WHERE role = 'customer' OR role_id = (SELECT id FROM roles WHERE role_name = 'customer' LIMIT 1)
LIMIT 1;

UPDATE furn_users SET
    email = 'employee@furniture.com',
    password_hash = '$2y$10$J0d9EGNvRolPUg9qOfY6NOy/LDkG5acfF.wlbfjesFx3/Dvn2yN5e',
    status = 'active', is_active = 1, failed_attempts = 0
WHERE role = 'employee' OR role_id = (SELECT id FROM roles WHERE role_name = 'employee' LIMIT 1)
LIMIT 1;
