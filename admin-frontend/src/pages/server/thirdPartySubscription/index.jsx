import React, { useEffect, useRef, useState } from 'react';
import {
  Button,
  Card,
  Col,
  Drawer,
  Form,
  Input,
  InputNumber,
  Popconfirm,
  Row,
  Space,
  Switch,
  Table,
  Tag,
  message,
} from 'antd';
import {
  fetchThirdPartySubscriptions,
  saveThirdPartySubscription,
  updateThirdPartySubscription,
  dropThirdPartySubscription,
  syncThirdPartySubscription,
  syncAllThirdPartySubscriptions,
} from '@/services/thirdPartySubscription';

const ThirdPartySubscriptionPage = () => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [edit, setEdit] = useState(null);
  const [drawerVisible, setDrawerVisible] = useState(false);
  const [form] = Form.useForm();

  const load = async () => {
    setLoading(true);
    try {
      const { data: res } = await fetchThirdPartySubscriptions({});
      setData(res || []);
    } catch (e) {
      message.error(e.message || '加载失败');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    load();
  }, []);

  const openCreate = () => {
    setEdit(null);
    form.resetFields();
    setDrawerVisible(true);
  };

  const openEdit = (row) => {
    setEdit(row);
    form.setFieldsValue({
      name: row.name,
      url: row.url,
      enabled: !!row.enabled,
      sort: row.sort,
      update_interval: row.update_interval,
    });
    setDrawerVisible(true);
  };

  const submit = async () => {
    const values = await form.validateFields();
    const payload = {
      ...values,
      enabled: values.enabled ? 1 : 0,
    };
    if (edit && edit.id) {
      payload.id = edit.id;
    }
    try {
      await saveThirdPartySubscription(payload);
      message.success('保存成功');
      setDrawerVisible(false);
      load();
    } catch (e) {
      message.error(e.message || '保存失败');
    }
  };

  const toggleEnabled = async (row, checked) => {
    try {
      await updateThirdPartySubscription({
        id: row.id,
        enabled: checked ? 1 : 0,
      });
      message.success('已更新');
      load();
    } catch (e) {
      message.error(e.message || '更新失败');
      load();
    }
  };

  const remove = async (row) => {
    try {
      await dropThirdPartySubscription(row.id);
      message.success('已删除');
      load();
    } catch (e) {
      message.error(e.message || '删除失败');
    }
  };

  const syncOne = async (row) => {
    try {
      const { data: res } = await syncThirdPartySubscription(row.id);
      if (res.success) {
        message.success(`同步成功，解析到 ${res.node_count} 个节点`);
      } else {
        message.error(res.error || '同步失败');
      }
      load();
    } catch (e) {
      message.error(e.message || '同步失败');
    }
  };

  const syncAll = async () => {
    setSyncing(true);
    try {
      const { data: res } = await syncAllThirdPartySubscriptions();
      if (!res || res.length === 0) {
        message.warning('没有可同步的订阅源');
        return;
      }
      const failed = res.filter((r) => !r.success);
      const ok = res.filter((r) => r.success);
      if (ok.length > 0) {
        message.success(`已同步 ${ok.length} 个订阅源`);
      }
      if (failed.length > 0) {
        message.error(`${failed.length} 个订阅源同步失败`);
      }
    } catch (e) {
      message.error(e.message || '同步失败');
    } finally {
      setSyncing(false);
      load();
    }
  };

  const columns = [
    {
      title: 'ID',
      dataIndex: 'id',
      width: 60,
    },
    {
      title: '名称',
      dataIndex: 'name',
    },
    {
      title: '订阅地址',
      dataIndex: 'url',
      ellipsis: true,
      render: (v) => v || '-',
    },
    {
      title: '节点数',
      dataIndex: 'node_count',
      width: 80,
      render: (v) => v ?? 0,
    },
    {
      title: '更新间隔(分钟)',
      dataIndex: 'update_interval',
      width: 130,
    },
    {
      title: '上次同步',
      dataIndex: 'fetched_at',
      width: 160,
      render: (v) => (v ? new Date(v * 1000).toLocaleString() : '-'),
    },
    {
      title: '状态',
      dataIndex: 'last_error',
      width: 160,
      render: (err, row) => {
        if (err) {
          return <Tag color="red">失败</Tag>;
        }
        return row.cache_exists ? <Tag color="green">已缓存</Tag> : <Tag>未同步</Tag>;
      },
    },
    {
      title: '启用',
      dataIndex: 'enabled',
      width: 70,
      render: (v, row) => (
        <Switch
          checked={!!v}
          size="small"
          onChange={(checked) => toggleEnabled(row, checked)}
        />
      ),
    },
    {
      title: '操作',
      width: 200,
      render: (_, row) => (
        <Space>
          <Button size="small" onClick={() => syncOne(row)}>
            同步
          </Button>
          <Button size="small" type="primary" onClick={() => openEdit(row)}>
            编辑
          </Button>
          <Popconfirm title="确认删除该订阅源？" onConfirm={() => remove(row)}>
            <Button size="small" danger>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <Card>
        <Row style={{ marginBottom: 16 }}>
          <Col>
            <Space>
              <Button type="primary" onClick={openCreate}>
                新增订阅源
              </Button>
              <Button loading={syncing} onClick={syncAll}>
                全部同步
              </Button>
            </Space>
          </Col>
        </Row>
        <Table
          rowKey="id"
          loading={loading}
          columns={columns}
          dataSource={data}
          pagination={false}
        />
      </Card>
      <Drawer
        title={edit && edit.id ? '编辑订阅源' : '新增订阅源'}
        width={520}
        open={drawerVisible}
        onClose={() => setDrawerVisible(false)}
      >
        <Form form={form} layout="vertical" style={{ marginTop: 16 }}>
          <Form.Item
            label="名称"
            name="name"
            rules={[{ required: true, message: '请输入订阅源名称' }]}
          >
            <Input placeholder="例如：HK 机场" />
          </Form.Item>
          <Form.Item
            label="订阅地址"
            name="url"
            rules={[
              { required: true, message: '请输入订阅地址' },
              { type: 'url', message: '请输入合法的 URL' },
            ]}
          >
            <Input placeholder="https://example.com/sub?token=xxx" />
          </Form.Item>
          <Form.Item label="更新间隔(分钟)" name="update_interval" initialValue={60}>
            <InputNumber min={1} max={10080} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="排序" name="sort" initialValue={0}>
            <InputNumber min={0} style={{ width: '100%' }} />
          </Form.Item>
          <Form.Item label="启用" name="enabled" valuePropName="checked" initialValue={true}>
            <Switch />
          </Form.Item>
          <Button type="primary" block onClick={submit}>
            保存
          </Button>
        </Form>
      </Drawer>
    </>
  );
};

export default ThirdPartySubscriptionPage;
