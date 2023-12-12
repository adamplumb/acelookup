#!/bin/bash

sed -i 's|datetime NOT NULL DEFAULT CURRENT_TIMESTAMP|timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP|g' ACE-World-Database-v0.9.276.sql
sed -i 's|GENERATED ALWAYS AS ((`obj_Cell_Id` >> 16)) VIRTUAL||g' ACE-World-Database-v0.9.276.sql

