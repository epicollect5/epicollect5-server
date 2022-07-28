import React from 'react';

import { Row, Col } from 'react-bootstrap';
import TableUsers from 'components/users/TableUsers';

const RowUsers = ({ data }) => {

    return (
        <Row className="animated fadeIn">
                    <Col xs={12} md={12} lg={12}>
                        <TableUsers stats={data} />
                    </Col>
                </Row>
    );
};

export default RowUsers;
