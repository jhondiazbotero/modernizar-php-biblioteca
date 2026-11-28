-- Amplía la columna de la clave para guardar hashes de password_hash (hasta 255
-- caracteres) además de los MD5 de 32. Las claves que ya existen no se tocan:
-- se convierten solas la próxima vez que su dueño inicie sesión.
ALTER TABLE bibliotecarios MODIFY clave VARCHAR(255) NOT NULL;
